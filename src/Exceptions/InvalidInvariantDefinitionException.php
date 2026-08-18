<?php

namespace Splitstack\Invariants\Exceptions;

use LogicException;

/**
 * Thrown when an Invariant is constructed with an inconsistent policy/argument combination.
 */
final class InvalidInvariantDefinitionException extends LogicException
{
    public static function autoCorrectWithoutTouches(): self
    {
        return new self('AutoCorrect policy requires at least one touched property to correct.');
    }

    public static function autoCorrectWithoutDefault(): self
    {
        return new self('AutoCorrect policy requires a default value to correct to.');
    }

    public static function lenientWithoutTouches(): self
    {
        return new self('Lenient policy requires at least one touched property to allow ignoring.');
    }
}
