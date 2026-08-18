<?php

namespace Splitstack\Invariants;

use Closure;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\Exceptions\InvalidInvariantDefinitionException;
use Splitstack\Invariants\Exceptions\MissingSubjectException;
use Splitstack\Invariants\Exceptions\StrictViolationException;
use Splitstack\Invariants\Exceptions\UncorrectablePropertyException;
use Throwable;

class Invariant
{
    /** @var list<string> */
    private array $violated = [];

    /** @var list<string> */
    private array $ignored = [];

    private bool $violatedLastAssert = false;

    /**
     * @param  list<string>  $touches  attribute names on the subject the rule is applied to
     */
    public function __construct(
        public readonly Closure $rule,
        public readonly string $message,
        public readonly mixed $default = null,
        public readonly array $touches = [],
        public readonly HydrationPolicy $policy = HydrationPolicy::Strict,
        public readonly mixed $model = null,
        public readonly mixed $event = null,
    ) {
        if ($this->policy === HydrationPolicy::AutoCorrect && $this->touches === []) {
            throw InvalidInvariantDefinitionException::autoCorrectWithoutTouches();
        }

        if ($this->policy === HydrationPolicy::AutoCorrect && $this->default === null) {
            throw InvalidInvariantDefinitionException::autoCorrectWithoutDefault();
        }

        if ($this->policy === HydrationPolicy::Lenient && $this->touches === []) {
            throw InvalidInvariantDefinitionException::lenientWithoutTouches();
        }
    }

    /**
     * @param  list<string>  $touches
     */
    public static function make(Closure $rule, string $message, mixed $default = null, ?array $touches = [], ?HydrationPolicy $policy = HydrationPolicy::Strict, mixed $event = null): self
    {
        return new self(
            rule: $rule,
            default: $default,
            message: $message,
            touches: $touches,
            policy: $policy,
            event: $event,
        );
    }

    public function on(mixed $model): self
    {
        return new self(
            touches: $this->touches,
            rule: $this->rule,
            default: $this->default,
            message: $this->message,
            policy: $this->policy,
            model: $model,
            event: $this->event,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            touches: $data['touches'],
            rule: $data['rule'],
            default: $data['default'],
            message: $data['message'],
            policy: $data['policy'] ?? HydrationPolicy::Strict,
            model: $data['model'] ?? null,
            event: $data['event'] ?? null,
        );
    }

    /**
     * @param  EnforcesInvariants|null  $subject  the model/aggregate the touched properties are read from
     */
    public function assert(?EnforcesInvariants $subject = null): void
    {
        $subject ??= $this->model;
        $rule = $this->rule;
        $this->violatedLastAssert = false;

        if ($this->touches !== []) {
            if ($subject === null) {
                throw MissingSubjectException::forTouches();
            }

            $passes = true;
            foreach ($this->touches as $property) {

                if ($this->ignored !== [] && in_array($property, $this->ignored, true)) {
                    continue;
                }

                $passes = $passes && (bool) $rule($subject->{$property});

                if (! $passes) {
                    $this->violated[] = $property;
                }
            }
        } else {
            $passes = (bool) $rule();
        }

        if (! $passes) {
            $this->handleViolation($subject);
        }
    }

    public function getIgnored(): array
    {
        return $this->ignored;
    }

    public function setIgnored(array $ignored): void
    {
        $this->ignored = $ignored;
    }

    public function wasViolated(): bool
    {
        return $this->violatedLastAssert;
    }

    private function handleViolation(?EnforcesInvariants $subject = null): void
    {
        $this->violatedLastAssert = true;

        try {
            match ($this->policy) {
                HydrationPolicy::Strict => throw new StrictViolationException($this->message),
                HydrationPolicy::Lenient => $this->handleLenient($subject),
                HydrationPolicy::Quarantine => $this->handleQuarantine($subject),
                HydrationPolicy::AutoCorrect => $this->handleAutoCorrect($subject),
            };
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $this->violated = [];
        }
    }

    private function handleAutoCorrect(?EnforcesInvariants $subject = null): void
    {
        if ($subject === null) {
            throw MissingSubjectException::forAutoCorrect();
        }

        foreach ($this->touches as $property) {
            if (property_exists($subject, $property) || method_exists($subject, '__set')) {
                $subject->{$property} = $this->default;
            } else {
                throw new UncorrectablePropertyException($property);
            }
        }
    }

    private function handleLenient(?EnforcesInvariants $subject = null): void
    {
        $this->ignored = $this->violated;
    }

    private function handleQuarantine(?EnforcesInvariants $subject = null): void
    {
        if ($subject === null) {
            throw MissingSubjectException::forQuarantine();
        }

        $subject->quarantine($this->message);
    }
}
