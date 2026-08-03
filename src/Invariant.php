<?php

namespace Splitstack\Invariants;

use Closure;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
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
        public readonly mixed $model = null
    ) {
        if ($this->policy === HydrationPolicy::AutoCorrect && $this->touches === []) {
            throw new \RuntimeException('AutoCorrect policy requires at least one touched property to correct.');
        }

        if ($this->policy === HydrationPolicy::AutoCorrect && $this->default === null) {
            throw new \RuntimeException('AutoCorrect policy requires a default value to correct to.');
        }

        if ($this->policy === HydrationPolicy::Lenient && $this->touches === []) {
            throw new \RuntimeException('Lenient policy requires at least one touched property to allow ignoring.');
        }
    }

    /**
     * @param  list<string>  $touches
     */
    public static function make(Closure $rule, string $message, mixed $default = null, ?array $touches = [], ?HydrationPolicy $policy = HydrationPolicy::Strict): self
    {
        return new self(
            rule: $rule,
            default: $default,
            message: $message,
            touches: $touches,
            policy: $policy,
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
                throw new \RuntimeException('An invariant with touches needs a subject (pass it to assert() or via on()).');
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
                HydrationPolicy::Strict => throw new \DomainException($this->message),
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
            throw new \RuntimeException('AutoCorrect policy requires a subject and property to correct.');
        }

        foreach ($this->touches as $property) {
            // A real declared property, or an attribute exposed through a magic
            // __set (Eloquent models, proxies) — property_exists() alone is false
            // for the latter even though the write succeeds.
            if (property_exists($subject, $property) || method_exists($subject, '__set')) {
                $subject->{$property} = $this->default;
            } else {
                throw new \RuntimeException("Property {$property} does not exist on the subject.");
            }
        }
    }

    private function handleLenient(?EnforcesInvariants $subject = null): void
    {
        // No throw, accept the value as is
        // Keep on the class to it stops throwing after encountered once
        $this->ignored = $this->violated;
    }

    private function handleQuarantine(?EnforcesInvariants $subject = null): void
    {
        if ($subject === null) {
            throw new \RuntimeException('Quarantine policy requires a subject implementing EnforcesInvariants.');
        }

        $subject->quarantine($this->message);
    }
}
