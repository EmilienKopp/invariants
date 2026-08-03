<?php

namespace Splitstack\Invariants\Support;

use Closure;
use ReflectionMethod;

/**
 * Bridges the package to whatever bus you point it at. It resolves the
 * dispatcher class and calls the configured method with the event. It does not
 * implement any dispatch logic of its own.
 *
 * Resolution order:
 *   1. If the target method is static, call it statically ($class::$via($e)).
 *   2. Else if a resolver is registered, use it to build the instance.
 *   3. Else `new $class()`.
 *
 * Wire a container in one line (e.g. in a Laravel service provider):
 *   EventDispatcher::resolveUsing(fn (string $c) => app($c));
 */
final class EventDispatcher
{
    /** @var (Closure(class-string): object)|null */
    private static ?Closure $resolver = null;

    /**
     * @param  (Closure(class-string): object)|null  $resolver  null resets to the default behaviour
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /**
     * @param  class-string  $dispatcher
     */
    public static function dispatch(string $dispatcher, string $via, object $event): void
    {
        if (! method_exists($dispatcher, $via)) {
            throw new \RuntimeException("Dispatcher [{$dispatcher}] has no method [{$via}].");
        }

        if ((new ReflectionMethod($dispatcher, $via))->isStatic()) {
            $dispatcher::$via($event);

            return;
        }

        $instance = self::$resolver !== null
            ? (self::$resolver)($dispatcher)
            : new $dispatcher;

        $instance->{$via}($event);
    }
}
