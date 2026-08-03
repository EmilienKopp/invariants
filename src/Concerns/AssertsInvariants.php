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
 * When the class carries a {@see DispatchesEvents} attribute, a violated
 * invariant dispatches an event through your bus. The event comes from, in
 * order: an event set on the rule (Invariant::make(event: ...), an object or a
 * class-string), an {@see InvariantEvent} attribute on the method, or the
 * built-in {@see InvariantViolated}. The package resolves the bus and calls it;
 * it never implements the dispatch itself.
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

        // Nothing to dispatch unless the method carries an #[InvariantEvent] or
        // the rule was built with an event via Invariant::make(event: ...).
        if ($declaration === null && $invariant->event === null) {
            return;
        }

        $event = $this->resolveEvent($declaration, $invariant, $method);

        EventDispatcher::dispatch($dispatcher->dispatcher, $dispatcher->via, $event);
    }

    private function resolveEvent(?InvariantEvent $declaration, Invariant $invariant, string $method): object
    {
        // An event set directly on the rule wins: pass it through if it is
        // already an instance, or build it from the touched fields if it is a
        // class-string.
        if ($invariant->event !== null) {
            return is_object($invariant->event)
                ? $invariant->event
                : new $invariant->event(...$this->collectEventFields($declaration));
        }

        if ($declaration?->event !== null) {
            return new $declaration->event(...$this->collectEventFields($declaration));
        }

        return new InvariantViolated(
            subject: static::class,
            invariant: $method,
            message: $invariant->message,
            data: $this->collectEventFields($declaration),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function collectEventFields(?InvariantEvent $declaration): array
    {
        if ($declaration === null) {
            return [];
        }

        $fields = [];
        foreach ($declaration->with as $property) {
            $fields[$property] = $this->{$property} ?? null;
        }

        return $fields;
    }
}
