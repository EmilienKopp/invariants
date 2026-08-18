<?php

namespace Splitstack\Invariants\Attributes;

use Attribute;

/**
 * Class-level declaration of which dispatcher to hand invariant events to
 * and which method to call on it.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DispatchesEvents
{
    /**
     * @param  class-string  $dispatcher  the bus/dispatcher to resolve
     * @param  string  $via  method to call on the dispatcher with the event
     */
    public function __construct(
        public string $dispatcher,
        public string $via = 'dispatch',
    ) {}
}
