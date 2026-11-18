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
use ReflectionMethod;
use ReflectionParameter;

/**
 * The SDK attributes, recognized by their fully-qualified name once `use`, alias and namespace are resolved.
 */
final class SdkAttributes
{
    private const CLASSES = [BidirektSchema::class, BidirektField::class, BidirektProvider::class, BidirektConsumer::class];

    /** @var array<class-string, list<string>> SDK attribute class => its parameters, read once: every annotation asks */
    private static array $parameters = [];

    /**
     * The names of an SDK attribute's parameters, in the order its positional arguments take them.
     *
     * @param class-string $class one of the SDK's attribute classes, never a class of the project
     * @return list<string>
     */
    public static function classToParameters(string $class): array
    {
        return self::$parameters[$class] ??= array_map(
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
                    $found[] = new SdkAttribute($name, ExpressionToSummary::argumentsToSummaries($attribute->args, $file), $file->locationOf($attribute));
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
     * An error for each attribute written with an SDK attribute's name, short or qualified, that PHP
     * resolves to the current namespace. One imported from another namespace is that one, and is
     * ignored.
     *
     * @return list<Diagnostic>
     */
    public static function fileToMissingUseErrors(ParsedFile $file): array
    {
        $errors = [];
        foreach ($file->nodes->attributes as $attribute) {
            $class = self::nameToUnimportedClass($attribute->name, $attribute);
            if ($class === null) {
                continue;
            }
            $errors[] = new Diagnostic(
                sprintf('#[%s] resolves to "%s", so it is not read: write %s', self::unimportedNameToWritten($attribute->name, $class), $attribute->name->toString(), self::unimportedNameToFix($attribute->name, $class)),
                $file->locationOf($attribute),
            );
        }

        return $errors;
    }

    /**
     * Whether the element has the SDK attribute `$class` written without its `use`.
     */
    public static function hasUnimportedAttribute(Node $element, string $class): bool
    {
        if (!property_exists($element, 'attrGroups') || !is_array($element->attrGroups)) {
            return false;
        }
        foreach ($element->attrGroups as $group) {
            if (!$group instanceof AttributeGroup) {
                continue;
            }
            foreach ($group->attrs as $attribute) {
                if (self::nameToUnimportedClass($attribute->name, $attribute) === $class) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The SDK class a name was written for, when PHP resolves it to the current namespace instead: a
     * short name without its `use`, or a qualified name without the leading `\`, like
     * `Bidirekt\PhpSdk\BidirektField` inside `namespace App`. Null for any other name.
     *
     * @param Node $node the node the name is written in
     */
    public static function nameToUnimportedClass(Name $name, Node $node): ?string
    {
        $original = $name->getAttribute('originalName');
        if (!$original instanceof Name || $original->isFullyQualified() || in_array($name->toLowerString(), array_map('strtolower', self::CLASSES), true)) {
            return null;
        }
        $class = self::writtenNameToClass($original);
        $unimported = Name::concat(NameContextVisitor::nodeToNameContext($node)->getNamespace(), $original);
        if ($class === null || $unimported === null || $unimported->toLowerString() !== $name->toLowerString()) {
            return null;
        }

        return $class;
    }

    /**
     * How to write a name nameToUnimportedClass() found: a short name takes its `use`, a qualified one
     * the leading `\`.
     */
    public static function unimportedNameToFix(Name $name, string $class): string
    {
        $original = $name->getAttribute('originalName');

        return $original instanceof Name && $original->isQualified() ? '\\' . $class : 'use ' . $class;
    }

    /**
     * A name nameToUnimportedClass() found, spelled as the SDK class: `bidirektField` reads
     * `BidirektField`, `phpsdk\bidirektfield` reads `PhpSdk\BidirektField`.
     */
    public static function unimportedNameToWritten(Name $name, string $class): string
    {
        $original = $name->getAttribute('originalName');
        $segments = $original instanceof Name ? count($original->getParts()) : 1;

        return implode('\\', array_slice(explode('\\', $class), -$segments));
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
        foreach ($file->nodes->attributes as $attribute) {
            $name = self::attributeToSdkName($attribute);
            $element = $file->nodes->elementOf($attribute);
            if ($name === null || $element === null || self::isReadOn($name, $element)) {
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

    /**
     * The SDK class whose fully-qualified name ends with the written name, segment by segment:
     * `BidirektField`, `PhpSdk\BidirektField` and `Bidirekt\PhpSdk\BidirektField` all name BidirektField.
     */
    private static function writtenNameToClass(Name $written): ?string
    {
        $suffix = '\\' . $written->toLowerString();
        foreach (self::CLASSES as $class) {
            if (str_ends_with('\\' . strtolower($class), $suffix)) {
                return $class;
            }
        }

        return null;
    }
}
