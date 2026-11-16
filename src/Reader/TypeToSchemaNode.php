<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\MapNode;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\PrimitiveNode;
use Bidirekt\PhpSdk\Model\RefNode;
use Bidirekt\PhpSdk\Model\SchemaNode;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Model\UnionNode;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ObjectShapeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

/**
 * Translates a type, native or from a docblock, into the contract's schema node by the table of R5.
 */
final class TypeToSchemaNode
{
    private const PRIMITIVES = ['string' => 'string', 'int' => 'integer', 'bool' => 'boolean', 'float' => 'number'];

    private const KEYWORDS = [
        'string', 'int', 'integer', 'bool', 'boolean', 'float', 'double', 'array', 'list', 'mixed', 'object',
        'iterable', 'callable', 'void', 'never', 'null', 'false', 'true', 'parent', 'resource', 'scalar', 'numeric',
    ];

    private const NATIVE_KEYWORDS = [
        'string', 'int', 'bool', 'float', 'array', 'mixed', 'object', 'iterable', 'callable', 'void', 'never', 'null',
        'false', 'true', 'parent',
    ];

    public function __construct(
        private ClassIndex $classes,
        private SchemaClasses $schemas,
    ) {}

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    public function writtenToSchemaNode(TypeContext $context): SchemaNode|array
    {
        $node = $this->typeToNode($context->written->node, $context);
        if (is_array($node) || $context->union === null || self::hasUnion($node)) {
            return $node;
        }

        return [new Diagnostic(
            sprintf(
                'cannot place %s in "%s": write a union of classes as its type, its items or its values',
                $context->form->placeUnion,
                $context->written->textOf($context->written->node),
            ),
            $context->location,
        )];
    }

    /**
     * Whether the type writes `null` at its top level, as in `?T` and `T|null`.
     */
    public static function isNullable(TypeNode $type): bool
    {
        return self::typeToParts($type)[1];
    }

    /**
     * The type without its top-level `null`, when that leaves a single type.
     */
    public static function withoutNull(TypeNode $type): ?TypeNode
    {
        $members = self::typeToParts($type)[0];
        if (count($members) !== 1) {
            return null;
        }

        return $members[0];
    }

    public static function isArrayKeyword(TypeNode $type): bool
    {
        return $type instanceof IdentifierTypeNode && strtolower($type->name) === 'array';
    }

    /**
     * A class named by `X::class` or by a type: a ref to its schema, or `integer` for an enum with int values.
     *
     * @return SchemaNode|list<Diagnostic>
     */
    public function classToSchemaNode(string $class, string $written, SourceLocation $location, AnnotationForm $form): SchemaNode|array
    {
        $lookup = $this->classes->lookup($class, $location);
        if ($lookup->errors !== []) {
            return $lookup->errors;
        }
        if ($lookup->declaration !== null) {
            return $this->declarationToSchemaNode($lookup->declaration, $written, $location, $form);
        }
        if (ClassIndex::isBuiltIn($class, true)) {
            return [self::untranslatable($written, $location, $form)];
        }

        return [new Diagnostic(
            sprintf('cannot find class "%s": declare it in the project or require its package with composer', $class),
            $location,
        )];
    }

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    private function typeToNode(TypeNode $type, TypeContext $context): SchemaNode|array
    {
        [$members, $nullable] = self::typeToParts($type);
        $node = $this->membersToNode($members, $type, $context);
        if ($node instanceof SchemaNode && $nullable) {
            $node->nullable = true;
        }

        return $node;
    }

    /**
     * @return array{list<TypeNode>, bool} the members besides `null`, and whether `null` is one of them
     */
    private static function typeToParts(TypeNode $type): array
    {
        if ($type instanceof NullableTypeNode) {
            return [self::typeToParts($type->type)[0], true];
        }
        if ($type instanceof IdentifierTypeNode && strtolower($type->name) === 'null') {
            return [[], true];
        }
        if (!$type instanceof UnionTypeNode) {
            return [[$type], false];
        }
        $members = [];
        $nullable = false;
        foreach ($type->types as $member) {
            [$memberTypes, $memberNullable] = self::typeToParts($member);
            array_push($members, ...$memberTypes);
            $nullable = $nullable || $memberNullable;
        }

        return [$members, $nullable];
    }

    /**
     * @param list<TypeNode> $members
     * @return SchemaNode|list<Diagnostic>
     */
    private function membersToNode(array $members, TypeNode $whole, TypeContext $context): SchemaNode|array
    {
        $classes = self::membersToClasses($members, $context->written);
        if ($context->union !== null && $classes !== null) {
            return $this->classesToDeclaredUnion($classes, $context->union, $context);
        }
        if (count($members) === 1) {
            return $this->memberToNode($members[0], $context);
        }
        $names = array_map(static fn(TypeNode $member): string => strtolower((string) $member), $members);
        sort($names);
        if ($names === ['float', 'int']) {
            return new PrimitiveNode('number');
        }
        if ($classes !== null) {
            return [new Diagnostic(
                sprintf(
                    'cannot translate union of classes "%s": declare each member\'s key with %s',
                    $context->written->textOf($whole),
                    $context->form->declareUnion,
                ),
                $context->location,
            )];
        }

        return [self::untranslatable($context->written->textOf($whole), $context->location, $context->form)];
    }

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    private function memberToNode(TypeNode $member, TypeContext $context): SchemaNode|array
    {
        $text = $context->written->textOf($member);
        if ($member instanceof IdentifierTypeNode) {
            return $this->identifierToNode($member, $text, $context);
        }
        if ($member instanceof ArrayTypeNode) {
            return $this->itemsToNode($member->type, $context);
        }
        if ($member instanceof GenericTypeNode) {
            return $this->genericToNode($member, $text, $context);
        }
        if ($member instanceof ArrayShapeNode) {
            return [self::shape('array', $text, $context)];
        }
        if ($member instanceof ObjectShapeNode) {
            return [self::shape('object', $text, $context)];
        }

        return [self::untranslatable($text, $context->location, $context->form)];
    }

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    private function identifierToNode(IdentifierTypeNode $identifier, string $text, TypeContext $context): SchemaNode|array
    {
        $name = strtolower($identifier->name);
        if (isset(self::PRIMITIVES[$name])) {
            return new PrimitiveNode(self::PRIMITIVES[$name]);
        }
        if ($name === 'self') {
            return $this->declarationToSchemaNode($context->scope->selfClass, $text, $context->location, $context->form);
        }
        if ($name === 'static') {
            return $this->declarationToSchemaNode($context->scope->staticClass, $text, $context->location, $context->form);
        }
        if ($name === 'array') {
            return [new Diagnostic(
                sprintf('cannot translate "%s" without the type of its items: write @var list<T> or array<string, T> %s, or declare it with %s', $text, $context->docblock, $context->form->declareArray),
                $context->location,
            )];
        }
        if (self::isKeyword($identifier, $context->written)) {
            return [self::untranslatable($text, $context->location, $context->form)];
        }

        return $this->classToSchemaNode($context->written->classNameToFullyQualified($identifier->name), $text, $context->location, $context->form);
    }

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    private function genericToNode(GenericTypeNode $generic, string $text, TypeContext $context): SchemaNode|array
    {
        $name = strtolower((string) $generic->type);
        $arguments = $generic->genericTypes;
        if ($name === 'list' && count($arguments) === 1) {
            return $this->itemsToNode($arguments[0], $context);
        }
        if ($name !== 'array') {
            return [new Diagnostic(
                sprintf('cannot translate generic type "%s": declare a non-generic class with %s and use it as the type', $text, $context->form->schemaAnnotation),
                $context->location,
            )];
        }
        $key = $arguments[0];
        if (count($arguments) === 2 && $key instanceof IdentifierTypeNode && strtolower($key->name) === 'string') {
            $values = $this->typeToNode($arguments[1], $context);
            if (is_array($values)) {
                return $values;
            }

            return new MapNode($values);
        }
        $items = $context->written->textOf($arguments[count($arguments) - 1]);

        return [new Diagnostic(
            sprintf('cannot translate "%s": write list<%s> for a list or array<string, %s> for a map', $text, $items, $items),
            $context->location,
        )];
    }

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    private function itemsToNode(TypeNode $items, TypeContext $context): SchemaNode|array
    {
        $node = $this->typeToNode($items, $context);
        if (is_array($node)) {
            return $node;
        }

        return new ArrayNode($node);
    }

    /**
     * @param list<TypeNode> $members
     * @return list<IdentifierTypeNode>|null the members when every one names a class
     */
    private static function membersToClasses(array $members, WrittenType $written): ?array
    {
        $classes = [];
        foreach ($members as $member) {
            if (!$member instanceof IdentifierTypeNode || (self::isKeyword($member, $written) && !in_array(strtolower($member->name), ['self', 'static'], true))) {
                return null;
            }
            $classes[] = $member;
        }
        if ($classes === []) {
            return null;
        }

        return $classes;
    }

    /**
     * @param list<IdentifierTypeNode> $classes
     * @return SchemaNode|list<Diagnostic>
     */
    private function classesToDeclaredUnion(array $classes, UnionNode $union, TypeContext $context): SchemaNode|array
    {
        $named = [];
        foreach ($classes as $class) {
            $node = $this->identifierToNode($class, $context->written->textOf($class), $context);
            if (is_array($node)) {
                return $node;
            }
            if (!$node instanceof RefNode) {
                return [new Diagnostic(
                    sprintf('cannot place "%s" in %s: write classes with %s as its members', $context->written->textOf($class), $context->form->placeUnion, $context->form->schemaAnnotation),
                    $context->location,
                )];
            }
            $named[] = [$node->schema, SchemaNaming::classToShortName($class->name)];
        }
        $schemas = array_map(static fn(array $each): string => $each[0], $named);
        $typeText = implode(' | ', $schemas);
        $what = $context->form->placeUnion;
        foreach ($union->members as $member) {
            if (!$member->node instanceof RefNode || in_array($member->node->schema, $schemas, true)) {
                continue;
            }
            $fix = 'remove its key or add it to the type';
            foreach ($named as [$schema, $code]) {
                if ($code === $member->node->schema) {
                    $fix = sprintf('write its name in the contract, "%s"', $schema);
                }
            }

            return [new Diagnostic(sprintf('%s lists "%s", which is not in "%s": %s', $what, $member->node->schema, $typeText, $fix), $context->location)];
        }
        foreach ($schemas as $schema) {
            $keys = array_filter($union->members, static fn($member): bool => $member->node instanceof RefNode && $member->node->schema === $schema);
            if (count($keys) === 0) {
                return [new Diagnostic(sprintf('%s misses "%s" of "%s": add a key for it', $what, $schema, $typeText), $context->location)];
            }
            if (count($keys) > 1) {
                return [new Diagnostic(sprintf('%s lists "%s" more than once: keep one key for it', $what, $schema), $context->location)];
            }
        }

        return clone $union;
    }

    /**
     * @return SchemaNode|list<Diagnostic>
     */
    private function declarationToSchemaNode(ClassDeclaration $class, string $written, SourceLocation $location, AnnotationForm $form): SchemaNode|array
    {
        $name = $this->schemas->classToName($class);
        if ($name !== null) {
            return new RefNode($name);
        }
        $description = SchemaClasses::nodeToDescription($class->node);
        if ($class->node instanceof Enum_) {
            $schema = $this->schemas->enumToSchema($class);
            if ($schema instanceof NamedSchema) {
                return new RefNode($schema->name);
            }
            if (is_array($schema)) {
                return $schema;
            }
            if ($class->node->scalarType !== null) {
                return new PrimitiveNode('integer');
            }

            return [new Diagnostic(sprintf('cannot translate %s, which has no values: give its cases string values', $description), $location)];
        }
        if ($class->node instanceof Interface_) {
            return [new Diagnostic(sprintf('cannot translate %s: use a class with %s as the type', $description, $form->schemaAnnotation), $location)];
        }
        if ($this->classes->isVendorClass($class)) {
            return [self::untranslatable($written, $location, $form)];
        }

        return [new Diagnostic(sprintf('cannot translate %s: %s', $description, $form->addSchema), $location)];
    }

    private static function hasUnion(SchemaNode $node): bool
    {
        if ($node instanceof UnionNode) {
            return true;
        }
        if ($node instanceof ArrayNode) {
            return self::hasUnion($node->items);
        }
        if ($node instanceof MapNode) {
            return self::hasUnion($node->values);
        }

        return false;
    }

    private static function isKeyword(IdentifierTypeNode $identifier, WrittenType $written): bool
    {
        $name = strtolower($identifier->name);
        $keywords = self::KEYWORDS;
        if ($written->native) {
            $keywords = self::NATIVE_KEYWORDS;
        }

        return str_contains($name, '-') || in_array($name, [...$keywords, 'self', 'static'], true);
    }

    private static function shape(string $kind, string $text, TypeContext $context): Diagnostic
    {
        return new Diagnostic(
            sprintf('cannot translate %s shape "%s": declare a class with %s and use it as the type', $kind, $text, $context->form->schemaAnnotation),
            $context->location,
        );
    }

    private static function untranslatable(string $text, SourceLocation $location, AnnotationForm $form): Diagnostic
    {
        return new Diagnostic(sprintf('cannot translate "%s" to a contract type: declare it with %s', $text, $form->declareType), $location);
    }
}
