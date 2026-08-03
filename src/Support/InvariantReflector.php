<?php

namespace Splitstack\Invariants\Support;

use ReflectionClass;
use ReflectionNamedType;
use Splitstack\Invariants\Attributes\DispatchesEvents;
use Splitstack\Invariants\Attributes\InvariantEvent;
use Splitstack\Invariants\Invariant;

/**
 * Discovers the invariants of a class by scanning for methods whose return
 * type is {@see Invariant}. Framework-free.
 */
final class InvariantReflector
{
    /** @var array<class-string, array<string, string>> */
    private static array $cache = [];

    /** @var array<class-string, DispatchesEvents|null> */
    private static array $dispatcherCache = [];

    /** @var array<class-string, array<string, InvariantEvent|null>> */
    private static array $eventCache = [];

    /**
     * @return array<string, string> map of label => method name
     */
    public static function scan(object $subject): array
    {
        $class = $subject::class;

        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $invariants = [];
        $ref = new ReflectionClass($subject);

        foreach ($ref->getMethods() as $method) {
            $returnType = $method->getReturnType();

            if ($returnType instanceof ReflectionNamedType && $returnType->getName() === Invariant::class) {
                $invariants[$method->getName()] = $method->getName();
            }
        }

        return self::$cache[$class] = $invariants;
    }

    /**
     * The class-level dispatcher declaration, or null if the class opts out.
     */
    public static function dispatcher(object $subject): ?DispatchesEvents
    {
        $class = $subject::class;

        if (array_key_exists($class, self::$dispatcherCache)) {
            return self::$dispatcherCache[$class];
        }

        $attribute = (new ReflectionClass($subject))->getAttributes(DispatchesEvents::class)[0] ?? null;

        return self::$dispatcherCache[$class] = $attribute?->newInstance();
    }

    /**
     * The event declaration on a given invariant method, or null if absent.
     */
    public static function event(object $subject, string $method): ?InvariantEvent
    {
        $class = $subject::class;

        if (isset(self::$eventCache[$class]) && array_key_exists($method, self::$eventCache[$class])) {
            return self::$eventCache[$class][$method];
        }

        $attribute = (new ReflectionClass($subject))->getMethod($method)->getAttributes(InvariantEvent::class)[0] ?? null;

        return self::$eventCache[$class][$method] = $attribute?->newInstance();
    }
}
