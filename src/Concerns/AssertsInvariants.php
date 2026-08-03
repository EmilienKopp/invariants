<?php

namespace Splitstack\Invariants\Concerns;

use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\Exceptions\InvariantViolationException;
use Splitstack\Invariants\Support\InvariantReflector;

/**
 * Opt-in convenience: auto-discovers every method on the class whose return
 * type is Invariant and runs them all through assertInvariants().
 *
 * This is the "auto-discovery" magic. It does NOT auto-invoke: call
 * assertInvariants() yourself at whatever boundary makes sense (end of a
 * mutating method, a service call, a request handler).
 *
 * @phpstan-require-implements EnforcesInvariants
 */
trait AssertsInvariants
{
    use HasQuarantine;

    public function assertInvariants(): void
    {
        foreach (InvariantReflector::scan($this) as $label => $method) {
            try {
                $this->{$method}()->assert($this);
            } catch (\DomainException|\LogicException $e) {
                throw new InvariantViolationException($label, $e->getMessage(), $e);
            }
        }
    }
}
