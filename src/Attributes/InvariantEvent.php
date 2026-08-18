<?php

namespace Splitstack\Invariants\Attributes;

use Attribute;

/**
 * Method-level declaration that a violated invariant should dispatch an event.
 * A custom `event` class is built with named args, so its constructor
 * parameters must match the `with` property names.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class InvariantEvent
{
    /**
     * @param  list<string>  $with  subject properties to read into the payload
     * @param  class-string|null  $event  optional event class to build instead of the built-in one
     */
    public function __construct(
        public array $with = [],
        public ?string $event = null,
    ) {}
}
