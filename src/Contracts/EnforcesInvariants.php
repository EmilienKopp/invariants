<?php

namespace Splitstack\Invariants\Contracts;

interface EnforcesInvariants
{
    /**
     * @param  list<string>|null  $touches  property names to filter by, or null for all
     */
    public function assertInvariants(?array $touches = null): void;

    public function quarantine(string $reason): void;

    public function isQuarantined(): bool;
}
