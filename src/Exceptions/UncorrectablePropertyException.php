<?php

namespace Splitstack\Invariants\Exceptions;

use RuntimeException;

/**
 * Thrown when AutoCorrect has nowhere to write the corrected value.
 */
final class UncorrectablePropertyException extends RuntimeException
{
    public function __construct(public readonly string $property)
    {
        parent::__construct("Property {$property} does not exist on the subject.");
    }
}
