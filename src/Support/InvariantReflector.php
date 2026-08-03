<?php

namespace Splitstack\Invariants\Support;

use ReflectionClass;
use ReflectionNamedType;
use Splitstack\Invariants\Invariant;

/**
 * Discovers the invariants of a class by scanning for methods whose return
 * type is {@see Invariant}. Framework-free: works on any object, not just
 * Eloquent models or proxies.
 */
final class InvariantReflector
{
    /** @var array<class-string, array<string, string>> */
    private static array $cache = [];

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
}
