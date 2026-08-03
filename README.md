# Splitstack Invariants

Framework-free invariant enforcement with hydration policies. Extracted from
[`splitstack/laravel-domainable`](https://github.com/EmilienKopp/laravel-domainable)
so the invariant system can be used anywhere: plain PHP, DTOs, API request
objects, or any codebase that does **not** use Eloquent model proxies.

- **Zero dependencies** beyond PHP 8.4.
- **Manual-first:** call `Invariant::make(...)->assert()` wherever you need it.
- **Optional auto-discovery:** a trait finds every invariant method on a class
  by reflection and runs them with one call.

## Install

```bash
composer require splitstack/invariants
```

## The two tiers

### Tier 1 — core, manual, no reflection

The whole point of the package. No proxy, no discovery, no framework.

```php
use Splitstack\Invariants\Invariant;

// Touchless rule: the closure captures the value, no subject needed.
Invariant::make(
    rule: fn () => $email !== '' && str_contains($email, '@'),
    message: 'Email must be valid',
)->assert(); // throws DomainException on failure (Strict policy)

// Touched rule: reads properties off a subject.
Invariant::make(
    rule: fn ($v) => $v >= 0,
    message: 'Balance cannot be negative',
    touches: ['balance'],
)->assert($account); // reads $account->balance
```

A **touched** rule needs a subject implementing `EnforcesInvariants` (see
below). A **touchless** rule needs nothing.

### Tier 2 — reflection auto-discovery (opt-in)

Define methods that return `Invariant`; call `assertInvariants()` once to run
them all. It discovers them by return type, so you never maintain a list.

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
            rule: fn ($v) => $v >= 0,
            message: 'Balance cannot be negative',
            touches: ['balance'],
        );
    }
}

(new Account(balance: -5))->assertInvariants();
// throws InvariantViolationException: "Invariant [balanceNonNegative] violated: ..."
```

> **Auto-discovery, not auto-invocation.** This package finds and runs your
> invariants when you *call* `assertInvariants()`. It does not wrap your methods
> to call it for you — that requires an interception proxy, which is out of
> scope here. `laravel-domainable`'s `Entity` proxy provides that automatic
> behavior on top of the same core.

Just want the quarantine state without reflection? Use `HasQuarantine` instead
of `AssertsInvariants`.

## Hydration policies

The `policy:` argument decides what happens when a rule fails:

| Policy        | Behavior on violation                                    | Requires            |
| ------------- | -------------------------------------------------------- | ------------------- |
| `Strict`      | throws `DomainException` (default)                       | —                   |
| `Lenient`     | ignores the violated property, silent afterward          | `touches`           |
| `Quarantine`  | calls `$subject->quarantine($message)`, does not throw   | subject             |
| `AutoCorrect` | rewrites each touched property to `default`               | `touches` + `default` |

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

## Contract

`EnforcesInvariants` is a three-method interface: `assertInvariants()`,
`quarantine()`, `isQuarantined()`. The `AssertsInvariants` trait satisfies all
three; `HasQuarantine` satisfies the last two. Implement it directly if you
want full control.

## Testing

```bash
composer install
composer test
```

## License

MIT
