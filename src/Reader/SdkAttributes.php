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
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use ReflectionMethod;
use ReflectionParameter;

/**
 * The SDK attributes, recognized by their fully-qualified name once `use`, alias and namespace are resolved.
 */
final class SdkAttributes
{
    private const CLASSES = [BidirektSchema::class, BidirektField::class, BidirektProvider::class, BidirektConsumer::class];

    /**
     * The names of an SDK attribute's parameters, in the order its positional arguments take them.
     *
     * @param class-string $class one of the SDK's attribute classes, never a class of the project
     * @return list<string>
     */
    public static function classToParameters(string $class): array
    {
        return array_map(
            static fn(ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionMethod($class, '__construct'))->getParameters(),
        );
    }

    /**
     * @return list<string> the SDK attributes' short names: BidirektSchema, BidirektField…
     */
    public static function shortNames(): array
    {
        return array_map(static fn(string $class): string => SchemaNaming::classToShortName($class), self::CLASSES);
    }

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
                return SchemaNaming::classToShortName($class);
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
            $unimported = Name::concat(NameContextVisitor::nodeToNameContext($attribute)->getNamespace(), $original);
            if ($class === null || $unimported === null || $unimported->toLowerString() !== $attribute->name->toLowerString()) {
                continue;
            }
            $warnings[] = new Diagnostic(
                sprintf('#[%s] resolves to "%s", so it is not read: write use %s', SchemaNaming::classToShortName($class), $attribute->name->toString(), $class),
                $file->locationOf($attribute),
            );
        }

        return $warnings;
    }

    /**
     * A warning for each SDK attribute on an element the reader does not read it on, like
     * #[BidirektProvider] on a class.
     *
     * @return list<Diagnostic>
     */
    public static function fileToMisplacedWarnings(ParsedFile $file): array
    {
        $warnings = [];
        foreach ((new NodeFinder())->findInstanceOf($file->statements, Attribute::class) as $attribute) {
            $name = self::attributeToSdkName($attribute);
            $group = $attribute->getAttribute('parent');
            $element = null;
            if ($group instanceof AttributeGroup) {
                $element = $group->getAttribute('parent');
            }
            if ($name === null || !$element instanceof Node || self::isReadOn($name, $element)) {
                continue;
            }
            $warnings[] = new Diagnostic(
                sprintf('#[%s] is on %s, so it is not read: put it on %s', $name, self::elementToDescription($element), self::targetsOf($name)),
                $file->locationOf($attribute),
            );
        }

        return $warnings;
    }

    private static function isReadOn(string $name, Node $element): bool
    {
        if ($name === 'BidirektSchema') {
            return $element instanceof ClassLike || $element instanceof FunctionLike;
        }
        if ($name === 'BidirektField') {
            return $element instanceof Stmt\Property || $element instanceof Param;
        }

        return $element instanceof FunctionLike;
    }

    private static function targetsOf(string $name): string
    {
        if ($name === 'BidirektSchema') {
            return 'a class, an enum, a method or a function';
        }
        if ($name === 'BidirektField') {
            return 'a property or a promoted constructor parameter';
        }

        return 'a method, a function or a closure';
    }

    private static function elementToDescription(Node $element): string
    {
        $descriptions = [
            [Enum_::class, 'an enum'],
            [Interface_::class, 'an interface'],
            [Trait_::class, 'a trait'],
            [ClassLike::class, 'a class'],
            [ClassMethod::class, 'a method'],
            [Function_::class, 'a function'],
            [Closure::class, 'a closure'],
            [ArrowFunction::class, 'an arrow function'],
            [Stmt\Property::class, 'a property'],
            [Stmt\ClassConst::class, 'a constant'],
            [EnumCase::class, 'an enum case'],
            [Param::class, 'a parameter'],
        ];
        foreach ($descriptions as [$class, $description]) {
            if ($element instanceof $class) {
                return $description;
            }
        }

        return 'this element';
    }

    private static function shortNameToClass(string $shortName): ?string
    {
        foreach (self::CLASSES as $class) {
            if (strtolower(SchemaNaming::classToShortName($class)) === strtolower($shortName)) {
                return $class;
            }
        }

        return null;
    }
}
