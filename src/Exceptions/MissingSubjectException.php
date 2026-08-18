<?php

namespace Splitstack\Invariants\Exceptions;

use LogicException;

/**
 * Thrown when an invariant needs a subject and none was provided.
 */
final class MissingSubjectException extends LogicException
{
    public static function forTouches(): self
    {
        return new self('An invariant with touches needs a subject (pass it to assert() or via on()).');
    }

    public static function forQuarantine(): self
    {
        return new self('Quarantine policy requires a subject implementing EnforcesInvariants.');
    }

    public static function forAutoCorrect(): self
    {
        return new self('AutoCorrect policy requires a subject and property to correct.');
    }
}
