<?php

namespace Splitstack\Invariants\Exceptions;

use RuntimeException;
use Throwable;

final class InvariantViolationException extends RuntimeException
{
    public function __construct(
        public readonly string $invariant,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Invariant [{$invariant}] violated: {$message}", 0, $previous);
    }
}
