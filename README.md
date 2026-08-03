# Splitstack Invariants

[![Tests](https://github.com/EmilienKopp/invariants/actions/workflows/tests.yml/badge.svg)](https://github.com/EmilienKopp/invariants/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/splitstack/invariants.svg)](https://packagist.org/packages/splitstack/invariants)
[![Total Downloads](https://img.shields.io/packagist/dt/splitstack/invariants.svg)](https://packagist.org/packages/splitstack/invariants)
[![PHP Version](https://img.shields.io/packagist/php-v/splitstack/invariants.svg)](https://packagist.org/packages/splitstack/invariants)
[![License](https://img.shields.io/packagist/l/splitstack/invariants.svg)](https://github.com/EmilienKopp/invariants/blob/main/LICENSE)

Framework-free invariant enforcement with hydration policies. For lightweight
domain modeling in PHP 8.4+.

- **Zero dependencies**
- **Manual-first:** call `Invariant::make(...)->assert()` wherever you need it.
- **Optional auto-discovery:** a trait finds every invariant method on a class
  by reflection and runs them with one call.

## Install

```bash
composer require splitstack/invariants
```

## Disclaimer

This package is in **alpha**. The API might evolve, and the docs might be incomplete. Please open an issue if you find a bug or have a feature request.
We can't guarantee there won't be breaking changes, but we will try to keep them to a minimum and document them in the changelog.

## The two tiers

### Tier 1: core, manual, no reflection

```php
use Splitstack\Invariants\Invariant;

// Touchless rule: the closure captures the local value, no subject needed.
$email = User::find($id)->email;
Invariant::make(
    rule: fn () => $email !== '' && str_contains($email, '@'),
    message: 'Email must be valid',
)->assert(); // throws DomainException on failure (policy: HydrationPolicy::Strict by default)

// Touched rule: reads properties off a subject.
Invariant::make(
    rule: fn ($v) => $v >= 0,
    message: 'Balance cannot be negative',
    touches: ['balance'],
)->assert($account); // reads $account->balance
```

A **touched** rule needs a subject implementing `EnforcesInvariants` (see
below). A **touchless** rule needs nothing.

### Tier 2: reflection auto-discovery (opt-in)

Define methods that return `Invariant`; call `assertInvariants()` once to run
them all. It discovers them by return type so you don't have to maintain a list.

```php
use Splitstack\Invariants\Concerns\AssertsInvariants;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\Invariant;

class Account implements EnforcesInvariants
{
    use AssertsInvariants; // adds assertInvariants(), quarantine(), isQuarantined()

    public function __construct(public int $balance = 0) {}

    protected function balanceNonNegative(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $v >= 0, // $v will resolve to $this->balance
            message: 'Balance cannot be negative',
            touches: ['balance'],
        );
    }
}

(new Account(balance: -5))->assertInvariants();
// throws InvariantViolationException: "Invariant [balanceNonNegative] violated: ..."
// $rule closure's `$v` resolves to `$this->balance` which is -5, so the rule fails.
```

> **Auto-discovery, not auto-invocation.** This package finds and runs your
> invariants when you _call_ `assertInvariants()`. It does not wrap your methods
> to call it for you. That would require an interception proxy, which is out of
> scope here. `laravel-domainable`'s `Entity` proxy provides that automatic
> behavior on top of the same core.

## Hydration policies

The `policy:` argument decides what happens when a rule fails:

| Policy        | Behavior on violation                                  | Requires              |
| ------------- | ------------------------------------------------------ | --------------------- |
| `Strict`      | throws `DomainException` (default)                     | —                     |
| `Lenient`     | ignores the violated property, silent afterward        | `touches`             |
| `Quarantine`  | calls `$subject->quarantine($message)`, does not throw | subject               |
| `AutoCorrect` | rewrites each touched property to `default`            | `touches` + `default` |

```php
use Splitstack\Invariants\HydrationPolicy;

Invariant::make(
    rule: fn ($v) => ctype_upper($v[0] ?? ''),
    message: 'Name must be capitalized',
    default: ucfirst($model->name),
    touches: ['name'],
    policy: HydrationPolicy::AutoCorrect,
)->assert($model);
```

`AutoCorrect` writes via `property_exists() || __set`, so it works on plain
objects with public properties and on objects exposing attributes through a
magic `__set` (Eloquent models, proxies).

## Quarantine and AutoCorrect need a subject

`assert()` is typed against `EnforcesInvariants`, so any invariant with
`touches` (which includes every `Quarantine` and `AutoCorrect` rule) must be
asserted against a subject implementing that contract. `AutoCorrect`
additionally needs the touched property to be writable (a public property or a
magic `__set`).

### Quarantine: mark, don't throw

`Quarantine` sets a flag on the instance and keeps going. It does **not** throw
and it does **not** remove anything. Acting on the flag is the caller's job:
this package has no repository, so nothing consumes `isQuarantined()` for you.

```php
use Splitstack\Invariants\Concerns\AssertsInvariants;
use Splitstack\Invariants\Contracts\EnforcesInvariants;
use Splitstack\Invariants\HydrationPolicy;
use Splitstack\Invariants\Invariant;

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
            policy: HydrationPolicy::Quarantine,
        );
    }
}

$account = new Account(balance: -5);
$account->assertInvariants(); // no throw
$account->isQuarantined();    // true

// You decide what the flag means, e.g. drop tainted rows from a batch:
$safe = array_filter($accounts, fn ($a) => ! $a->isQuarantined());
```

Just want the quarantine flag without reflection-based discovery? Use
`HasQuarantine` instead of `AssertsInvariants`.

### AutoCorrect: repair in place

`AutoCorrect` rewrites each touched property to `default` when the rule fails,
then continues without throwing. It requires both `touches` and a non-null
`default` (the value written).

```php
protected function balanceNonNegative(): Invariant
{
    return Invariant::make(
        rule: fn ($v) => $v >= 0,
        message: 'Balance cannot be negative',
        default: 0,
        touches: ['balance'],
        policy: HydrationPolicy::AutoCorrect,
    );
}

$account = new Account(balance: -5);
$account->assertInvariants(); // balance is now 0, no exception thrown
```

Very useful for batch processing or when you have legacy data that you KNOW is wrong but still want to manipulate.

## Dispatching events on violation

You can have a violated invariant fire an event through your own bus. The
package never ships a bus and never implements the dispatch: you point it at a
dispatcher class (and optionally a method name), and it resolves and calls it
for you when `assertInvariants()` runs.

Declare the dispatcher on the class and the payload on the invariant method:

```php
use Splitstack\Invariants\Attributes\DispatchesEvents;
use Splitstack\Invariants\Attributes\InvariantEvent;

#[DispatchesEvents(App\EventBus::class, via: 'dispatch')] // via defaults to 'dispatch'
class ApprovalRequestEntity implements EnforcesInvariants
{
    use AssertsInvariants;

    #[InvariantEvent(['id', 'status', 'applicantUserId'])]
    protected function statusRequiresInProgress(): Invariant
    {
        return Invariant::make(
            rule: fn ($v) => $this->status === ApprovalRequestStatus::IN_PROGRESS,
            message: 'Not in progress',
            touches: ['status'],
            policy: HydrationPolicy::Quarantine,
        );
    }
}
```

When `statusRequiresInProgress` is violated (for **any** policy, including the
non-throwing ones), the package reads the named subject fields into a payload
and hands it to `EventBus::dispatch($event)`.

**Payload shape.** With just a field list, a built-in
`Splitstack\Invariants\Events\InvariantViolated` event is dispatched:

```php
new InvariantViolated(
    subject: ApprovalRequestEntity::class,
    invariant: 'statusRequiresInProgress',
    message: 'Not in progress',
    data: ['id' => 1, 'status' => 'draft', 'applicantUserId' => 7],
);
```

To dispatch your own domain event instead, name it. It's built with named args
pulled from `with`, so its constructor params must match the property names:

```php
#[InvariantEvent(with: ['id', 'status'], event: App\Events\ApprovalStalled::class)]
// dispatches: new ApprovalStalled(id: ..., status: ...)
```

**Setting the event on the rule.** You can skip the method attribute and hand
`Invariant::make()` the event directly, either as a ready-made instance or a
class-string. This wins over an `#[InvariantEvent]` on the method:

```php
protected function statusRequiresInProgress(): Invariant
{
    return Invariant::make(
        rule: fn ($v) => $v === ApprovalRequestStatus::IN_PROGRESS,
        message: 'Not in progress',
        touches: ['status'],
        policy: HydrationPolicy::Quarantine,
        event: new App\Events\ApprovalStalled(id: $this->id, status: $this->status),
    );
}
```

An instance is dispatched as-is. A class-string is built with named args from an
`#[InvariantEvent([...])]` field list if one is present, otherwise with no args.

An invariant with no `#[InvariantEvent]` and no `event` on the rule dispatches
nothing, even on a class that declares `#[DispatchesEvents]`.

**Resolving the dispatcher.** By default, a static `via` method is called
statically (`EventBus::dispatch($event)`); otherwise the package does
`new EventBus()`. To use a container, register a resolver once at boot:

```php
use Splitstack\Invariants\Support\EventDispatcher;

// e.g. in a Laravel service provider
EventDispatcher::resolveUsing(fn (string $class) => app($class));
```

## Contract

`EnforcesInvariants` is a three-method interface: `assertInvariants()`,
`quarantine()`, `isQuarantined()`. The `AssertsInvariants` trait satisfies all
three; `HasQuarantine` satisfies the last two.

## Testing

```bash
composer install
composer test
```

## License

MIT
