<?php

namespace Splitstack\Invariants\Support;

use Closure;
use ReflectionMethod;

/**
 * Resolves the dispatcher class and calls the configured method with the event.
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
