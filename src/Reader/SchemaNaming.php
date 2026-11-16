<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * The names a schema and a field take in the contract when the attribute does not declare one.
 */
final class SchemaNaming
{
    public static function classToShortName(string $class): string
    {
        $separator = strrpos($class, '\\');
        if ($separator === false) {
            return $class;
        }

        return substr($class, $separator + 1);
    }

    public static function schemaToName(?string $declared, string $class): string
    {
        return $declared ?? self::classToShortName($class);
    }

    public static function fieldToName(?string $declared, string $member): string
    {
        return $declared ?? $member;
    }
}
