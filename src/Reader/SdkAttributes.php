<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;
use Bidirekt\PhpSdk\Model\Diagnostic;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;

/**
 * The SDK attributes, recognized by their fully-qualified name once `use`, alias and namespace are resolved.
 */
final class SdkAttributes
{
    public const SCHEMA_PARAMETERS = ['name', 'fields'];

    public const FIELD_PARAMETERS = ['name', 'type', 'items', 'values', 'optional', 'nullable', 'description', 'union'];

    private const CLASSES = [BidirektSchema::class, BidirektField::class, BidirektProvider::class, BidirektConsumer::class];

    /**
     * @param Node $element a node that takes attributes: class-like, property, parameter, method, function, closure…
     * @return list<SdkAttribute> in the order they are written
     */
    public static function elementToSdkAttributes(Node $element, ParsedFile $file): array
    {
        if (!property_exists($element, 'attrGroups') || !is_array($element->attrGroups)) {
            return [];
        }
        $found = [];
        foreach ($element->attrGroups as $group) {
            if (!$group instanceof AttributeGroup) {
                continue;
            }
            foreach ($group->attrs as $attribute) {
                $name = self::attributeToSdkName($attribute);
                if ($name !== null) {
                    $found[] = new SdkAttribute($name, $attribute, $file->locationOf($attribute));
                }
            }
        }

        return $found;
    }

    /**
     * @return string|null the SDK attribute's short name, or null for any other attribute
     */
    public static function attributeToSdkName(Attribute $attribute): ?string
    {
        $resolved = strtolower($attribute->name->toString());
        foreach (self::CLASSES as $class) {
            if (strtolower($class) === $resolved) {
                return self::classToShortName($class);
            }
        }

        return null;
    }

    /**
     * A warning for each attribute written with an SDK attribute's short name and no `use` for it, which
     * PHP resolves to the current namespace. One imported from another namespace is that one, and is
     * not warned.
     *
     * @return list<Diagnostic>
     */
    public static function fileToMissingUseWarnings(ParsedFile $file): array
    {
        $warnings = [];
        foreach ((new NodeFinder())->findInstanceOf($file->statements, Attribute::class) as $attribute) {
            $original = $attribute->name->getAttribute('originalName');
            if (!$original instanceof Name || !$original->isUnqualified() || self::attributeToSdkName($attribute) !== null) {
                continue;
            }
            $class = self::shortNameToClass($original->toString());
            if ($class === null || NameScope::ofNode($attribute)->hasAlias($original->toString())) {
                continue;
            }
            $warnings[] = new Diagnostic(
                sprintf('#[%s] resolves to "%s", so it is not read: write use %s', self::classToShortName($class), $attribute->name->toString(), $class),
                $file->locationOf($attribute),
            );
        }

        return $warnings;
    }

    private static function shortNameToClass(string $shortName): ?string
    {
        foreach (self::CLASSES as $class) {
            if (strtolower(self::classToShortName($class)) === strtolower($shortName)) {
                return $class;
            }
        }

        return null;
    }

    private static function classToShortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }
}
