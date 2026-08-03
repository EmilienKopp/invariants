# Splitstack Invariants

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
