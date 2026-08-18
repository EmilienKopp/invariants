<?php

use Splitstack\Invariants\Attributes\DispatchesEvents;
use Splitstack\Invariants\Attributes\InvariantEvent;
use Splitstack\Invariants\Concerns\AssertsInvariants;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\Events\InvariantViolated;
use Splitstack\Invariants\Exceptions\InvariantViolationException;
use Splitstack\Invariants\HydrationPolicy;
use Splitstack\Invariants\Invariant;
use Splitstack\Invariants\Support\EventDispatcher;

class SpyBus
{
    /** @var list<object> */
    public static array $events = [];

    public function dispatch(object $event): void
    {
        self::$events[] = $event;
    }

    public static function fire(object $event): void
    {
        self::$events[] = $event;
    }

    public static function reset(): void
    {
        self::$events = [];
    }
}

final class ApprovalStalled
{
    public function __construct(
        public readonly mixed $id,
        public readonly mixed $status,
    ) {}
}

#[DispatchesEvents(SpyBus::class)]
class QuarantinedApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(
        public int $id = 1,
        public string $status = 'draft',
        public int $applicantUserId = 7,
    ) {}

    #[InvariantEvent(['id', 'status', 'applicantUserId'])]
    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
        );
    }
}

#[DispatchesEvents(SpyBus::class)]
class NamedEventApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public int $id = 1, public string $status = 'draft') {}

    #[InvariantEvent(with: ['id', 'status'], event: ApprovalStalled::class)]
    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
        );
    }
}

#[DispatchesEvents(SpyBus::class)]
class StrictApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public string $status = 'draft') {}

    #[InvariantEvent(['status'])]
    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
        );
    }
}

#[DispatchesEvents(SpyBus::class, via: 'fire')]
class StaticDispatchApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public string $status = 'draft') {}

    #[InvariantEvent(['status'])]
    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
        );
    }
}

#[DispatchesEvents(SpyBus::class)]
class DirectEventApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public int $id = 1, public string $status = 'draft') {}

    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
            event: new ApprovalStalled(id: 99, status: 'draft'),
        );
    }
}

#[DispatchesEvents(SpyBus::class)]
class DirectEventClassApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public int $id = 5, public string $status = 'draft') {}

    #[InvariantEvent(['id', 'status'])]
    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
            event: ApprovalStalled::class,
        );
    }
}

#[DispatchesEvents(SpyBus::class)]
class SilentApproval implements EnforcesInvariants
{
    use AssertsInvariants;

    public function __construct(public string $status = 'draft') {}

    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v === 'in_progress',
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
        );
    }
}

beforeEach(function () {
    SpyBus::reset();
    EventDispatcher::resolveUsing(null);
});

it('dispatches the built-in event with the named fields on violation', function () {
    (new QuarantinedApproval)->assertInvariants();

    expect(SpyBus::$events)->toHaveCount(1);

    $event = SpyBus::$events[0];
    expect($event)->toBeInstanceOf(InvariantViolated::class)
        ->and($event->subject)->toBe(QuarantinedApproval::class)
        ->and($event->invariant)->toBe('statusRequiresInProgress')
        ->and($event->message)->toBe('Not in progress')
        ->and($event->data)->toBe(['id' => 1, 'status' => 'draft', 'applicantUserId' => 7]);
});

it('builds and dispatches a named event with named args', function () {
    (new NamedEventApproval(id: 42, status: 'draft'))->assertInvariants();

    expect(SpyBus::$events)->toHaveCount(1);

    $event = SpyBus::$events[0];
    expect($event)->toBeInstanceOf(ApprovalStalled::class)
        ->and($event->id)->toBe(42)
        ->and($event->status)->toBe('draft');
});

it('does not dispatch when the invariant holds', function () {
    (new QuarantinedApproval(status: 'in_progress'))->assertInvariants();

    expect(SpyBus::$events)->toHaveCount(0);
});

it('dispatches for a Strict violation before throwing', function () {
    expect(fn () => (new StrictApproval)->assertInvariants())
        ->toThrow(InvariantViolationException::class);

    expect(SpyBus::$events)->toHaveCount(1)
        ->and(SpyBus::$events[0])->toBeInstanceOf(InvariantViolated::class);
});

it('calls a static dispatch method statically', function () {
    (new StaticDispatchApproval)->assertInvariants();

    expect(SpyBus::$events)->toHaveCount(1);
});

it('uses a registered resolver to build the dispatcher instance', function () {
    $built = [];
    EventDispatcher::resolveUsing(function (string $class) use (&$built) {
        $built[] = $class;

        return new $class;
    });

    (new QuarantinedApproval)->assertInvariants();

    expect($built)->toBe([SpyBus::class])
        ->and(SpyBus::$events)->toHaveCount(1);
});

it('dispatches an event instance set directly on the rule, without a method attribute', function () {
    (new DirectEventApproval)->assertInvariants();

    expect(SpyBus::$events)->toHaveCount(1);

    $event = SpyBus::$events[0];
    expect($event)->toBeInstanceOf(ApprovalStalled::class)
        ->and($event->id)->toBe(99)
        ->and($event->status)->toBe('draft');
});

it('builds a class-string event set on the rule from the attribute fields', function () {
    (new DirectEventClassApproval(id: 5, status: 'draft'))->assertInvariants();

    expect(SpyBus::$events)->toHaveCount(1);

    $event = SpyBus::$events[0];
    expect($event)->toBeInstanceOf(ApprovalStalled::class)
        ->and($event->id)->toBe(5)
        ->and($event->status)->toBe('draft');
});

it('dispatches nothing when the invariant method has no InvariantEvent attribute', function () {
    $subject = new SilentApproval;
    $subject->assertInvariants();

    expect($subject->isQuarantined())->toBeTrue()
        ->and(SpyBus::$events)->toHaveCount(0);
});
