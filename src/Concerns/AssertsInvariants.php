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
 * Auto-discovers every method returning Invariant and runs them through
 * assertInvariants(). Does not auto-invoke; call assertInvariants() yourself.
 *
 * @phpstan-require-implements EnforcesInvariants
 */
trait AssertsInvariants
{
    use HasQuarantine;

    /**
     * Runs every discovered invariant, optionally filtered by touched properties.
     *
     * @param  list<string>|null  $touches  property names to filter by, or null for all
     */
    public function assertInvariants(?array $touches = null): void
    {
        $dispatcher = InvariantReflector::dispatcher($this);

        foreach (InvariantReflector::scan($this) as $label => $method) {
            $invariant = $this->{$method}();

            if ($touches !== null && array_intersect($touches, $invariant->touches) === []) {
                continue;
            }

            try {
                $invariant->assert($this);
            } catch (\DomainException|\LogicException $e) {
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

        if ($declaration === null && $invariant->event === null) {
            return;
        }

        $event = $this->resolveEvent($declaration, $invariant, $method);

        EventDispatcher::dispatch($dispatcher->dispatcher, $dispatcher->via, $event);
    }

    private function resolveEvent(?InvariantEvent $declaration, Invariant $invariant, string $method): object
    {
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
