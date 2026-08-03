<?php

namespace Splitstack\Invariants\Attributes;

use Attribute;

/**
 * Method-level declaration that a violated invariant should dispatch an event.
 *
 * Two shapes:
 *
 *   // Built-in InvariantViolated event carrying the named subject fields:
 *   #[InvariantEvent(['id', 'status', 'applicantUserId'])]
 *
 *   // Your own domain event, built with named args pulled from the subject:
 *   #[InvariantEvent(with: ['id', 'status'], event: ApprovalStalled::class)]
 *
 * When `event` is set, the class is instantiated as `new $event(...$fields)`
 * where `$fields` is the `with` map keyed by property name, so the event's
 * constructor parameters must match the property names.
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
