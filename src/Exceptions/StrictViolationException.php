<?php

namespace Splitstack\Invariants\Exceptions;

use DomainException;

/**
 * Thrown by the Strict policy when an invariant is violated.
 */
final class StrictViolationException extends DomainException {}
