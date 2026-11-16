<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt;

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

    /**
     * The member's name in the code, a property statement's being its first property's.
     */
    public static function memberToName(Stmt\Property|PropertyItem|Param $member): string
    {
        if ($member instanceof Stmt\Property) {
            return $member->props[0]->name->toString();
        }
        if ($member instanceof PropertyItem) {
            return $member->name->toString();
        }
        if ($member->var instanceof Variable && is_string($member->var->name)) {
            return $member->var->name;
        }

        return '';
    }
}
