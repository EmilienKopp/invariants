<?php

namespace Splitstack\Invariants\Events;

use Splitstack\Invariants\Attributes\InvariantEvent;

/**
 * Default event dispatched on violation when no custom event class is named.
 */
final class InvariantViolated
{
    /**
     * @param  class-string  $subject  the class the invariant belongs to
     * @param  string  $invariant  the invariant method (label) that failed
     * @param  array<string, mixed>  $data  named subject fields from the attribute's `with`
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $invariant,
        public readonly string $message,
        public readonly array $data = [],
    ) {}
}
