<?php

namespace Splitstack\Invariants\Concerns;

use Splitstack\Invariants\Contracts\EnforcesInvariants;

/**
 * Quarantine state for a subject implementing {@see EnforcesInvariants}.
 */
trait HasQuarantine
{
    private bool $quarantined = false;

    /** @var list<string> */
    private array $quarantineReasons = [];

    public function quarantine(string $reason): void
    {
        $this->quarantined = true;
        $this->quarantineReasons[] = $reason;
    }

    public function isQuarantined(): bool
    {
        return $this->quarantined;
    }

    /**
     * @return list<string>
     */
    public function quarantineReasons(): array
    {
        return $this->quarantineReasons;
    }
}
