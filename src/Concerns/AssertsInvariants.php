<?php

namespace Splitstack\Invariants\Concerns;

use Splitstack\Invariants\Attributes\DispatchesEvents;
use Splitstack\Invariants\Attributes\InvariantEvent;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\Events\InvariantViolated;
use Splitstack\Invariants\Exceptions\InvariantViolationException;
use Splitstack\Invariants\Invariant;
use Splitstack\Invariants\Support\EventDispatcher;
use Splitstack\Invariants\Support\InvariantReflector;

/**
 * Opt-in convenience: auto-discovers every method on the class whose return
 * type is Invariant and runs them all through assertInvariants().
 *
 * This is the "auto-discovery" magic. It does NOT auto-invoke: call
 * assertInvariants() yourself at whatever boundary makes sense (end of a
 * mutating method, a service call, a request handler).
 *
 * When the class carries a {@see DispatchesEvents} attribute and an invariant
 * method carries an {@see InvariantEvent}
 * attribute, a violation of that invariant dispatches an event through your
 * bus. The package resolves the bus and calls it; it never implements the
 * dispatch itself.
 *
 * @phpstan-require-implements EnforcesInvariants
 */
trait AssertsInvariants
{
    use HasQuarantine;

    public function assertInvariants(): void
    {
        $dispatcher = InvariantReflector::dispatcher($this);

        foreach (InvariantReflector::scan($this) as $label => $method) {
            $invariant = $this->{$method}();

            try {
                $invariant->assert($this);
            } catch (\DomainException|\LogicException $e) {
                // A Strict violation throws before we can read wasViolated(),
                // so dispatch here too, then re-raise as the package exception.
                $this->dispatchInvariantEvent($dispatcher, $method, $invariant);

                throw new InvariantViolationException($label, $e->getMessage(), $e);
            }

            if ($invariant->wasViolated()) {
                $this->dispatchInvariantEvent($dispatcher, $method, $invariant);
            }
        }
    }

    private function dispatchInvariantEvent(?DispatchesEvents $dispatcher, string $method, Invariant $invariant): void
    {
        if ($dispatcher === null) {
            return;
        }

        $declaration = InvariantReflector::event($this, $method);

        if ($declaration === null) {
            return;
        }

        $fields = [];
        foreach ($declaration->with as $property) {
            $fields[$property] = $this->{$property} ?? null;
        }

        $event = $declaration->event !== null
            ? new $declaration->event(...$fields)
            : new InvariantViolated(
                subject: static::class,
                invariant: $method,
                message: $invariant->message,
                data: $fields,
            );

        EventDispatcher::dispatch($dispatcher->dispatcher, $dispatcher->via, $event);
    }
}
