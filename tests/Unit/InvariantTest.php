<?php

use Splitstack\Invariants\Concerns\AssertsInvariants;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\Exceptions\InvariantViolationException;
use Splitstack\Invariants\HydrationPolicy;
use Splitstack\Invariants\Invariant;

class Subject implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(
        public mixed $value = null,
        public string $name = '',
    ) {}
}

class Account implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public int $balance = 0) {}

    protected function balanceNonNegative(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v >= 0,
            message: 'Balance cannot be negative',
            touches: ['balance'],
        );
    }
}

/**
 * Readable via magic __get but with no declared property and no __set,
 * so AutoCorrect has nowhere to write the correction.
 */
class MagicReadSubject implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __get(string $name): mixed
    {
        return null;
    }
}

it('passes a touchless rule without a subject', function () {
    Invariant::make(rule: fn () => true, message: 'ok')->assert();
})->throwsNoExceptions();

it('throws DomainException on a failing Strict touchless rule', function () {
    Invariant::make(rule: fn () => false, message: 'nope')->assert();
})->throws(DomainException::class, 'nope');

it('throws DomainException on a failing Strict touched rule', function () {
    $subject = new Subject(value: -1);

    Invariant::make(
        rule: fn ($v) => $v >= 0,
        message: 'must be non-negative',
        touches: ['value'],
    )->assert($subject);
})->throws(DomainException::class, 'must be non-negative');

it('requires a subject for a touched rule', function () {
    Invariant::make(
        rule: fn ($v) => $v >= 0,
        message: 'x',
        touches: ['value'],
    )->assert();
})->throws(RuntimeException::class);

it('AutoCorrect rewrites a violated property to the default', function () {
    $subject = new Subject(value: -1);

    Invariant::make(
        rule: fn ($v) => $v >= 0,
        message: 'x',
        default: 42,
        touches: ['value'],
        policy: HydrationPolicy::AutoCorrect,
    )->assert($subject);

    expect($subject->value)->toBe(42);
});

it('AutoCorrect throws when the property does not exist and there is no __set', function () {
    $subject = new MagicReadSubject;

    Invariant::make(
        rule: fn ($v) => false,
        message: 'x',
        default: 1,
        touches: ['ghost'],
        policy: HydrationPolicy::AutoCorrect,
    )->assert($subject);
})->throws(RuntimeException::class, 'Property ghost does not exist on the subject.');

it('Lenient does not throw and stays silent on repeat', function () {
    $subject = new Subject(value: -1);

    $invariant = Invariant::make(
        rule: fn ($v) => $v >= 0,
        message: 'x',
        touches: ['value'],
        policy: HydrationPolicy::Lenient,
    );

    $invariant->assert($subject);
    $invariant->assert($subject);

    expect($subject->isQuarantined())->toBeFalse()
        ->and($invariant->getIgnored())->toBe(['value']);
});

it('Quarantine marks the subject instead of throwing', function () {
    $subject = new Subject(value: -1);

    Invariant::make(
        rule: fn ($v) => $v >= 0,
        message: 'bad value',
        touches: ['value'],
        policy: HydrationPolicy::Quarantine,
    )->assert($subject);

    expect($subject->isQuarantined())->toBeTrue();
});

it('captures $this as the subject when member of a class', function () {
    $myclass = new class implements EnforcesInvariants
    {
        use AssertsInvariants;

        public function __construct(public int $value = -1) {}

        protected function valueNonNegative(): Invariant
        {
            return Invariant::make(
                rule: fn ($v) => $v >= 0,
                message: 'value must be non-negative',
                touches: ['value'],
            );
        }
    };

    $myclass->assertInvariants();
})->throws(InvariantViolationException::class, 'Invariant [valueNonNegative] violated: value must be non-negative');

it('rejects AutoCorrect without touches', function () {
    Invariant::make(rule: fn () => true, message: 'x', default: 1, policy: HydrationPolicy::AutoCorrect);
})->throws(RuntimeException::class);

it('rejects AutoCorrect without a default', function () {
    Invariant::make(rule: fn () => true, message: 'x', touches: ['value'], policy: HydrationPolicy::AutoCorrect);
})->throws(RuntimeException::class);

it('rejects Lenient without touches', function () {
    Invariant::make(rule: fn () => true, message: 'x', policy: HydrationPolicy::Lenient);
})->throws(RuntimeException::class);

it('auto-discovers and runs invariant methods via assertInvariants()', function () {
    (new Account(balance: -5))->assertInvariants();
})->throws(InvariantViolationException::class, 'Invariant [balanceNonNegative] violated: Balance cannot be negative');

it('passes assertInvariants() when all invariants hold', function () {
    (new Account(balance: 10))->assertInvariants();
})->throwsNoExceptions();

it('exposes the violated invariant label on the exception', function () {
    try {
        (new Account(balance: -1))->assertInvariants();
    } catch (InvariantViolationException $e) {
        expect($e->invariant)->toBe('balanceNonNegative');

        return;
    }

    $this->fail('Expected InvariantViolationException.');
});
