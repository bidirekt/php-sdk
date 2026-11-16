<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektSchema;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\EnumNode;
use Bidirekt\PhpSdk\Model\NamedSchema;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * The schema name of each class with `#[BidirektSchema]`, and the enum schemas, built once per enum
 * so every field that references one shares it.
 */
final class SchemaClasses
{
    /** @var array<int, string> class node id => schema name */
    private array $classNames = [];

    /** @var array<int, string> enum node id => name declared by its #[BidirektSchema] */
    private array $enumNames = [];

    /** @var array<int, NamedSchema|list<Diagnostic>> enum node id => schema or why it cannot be one */
    private array $enumSchemas = [];

    public function __construct(
        private ConstantExpressionToValue $evaluator,
        private SchemaTags $tags,
    ) {}

    public function addClass(ClassLike $node, string $name): void
    {
        $this->classNames[spl_object_id($node)] = $name;
    }

    public function classToName(ClassLike $node): ?string
    {
        return $this->classNames[spl_object_id($node)] ?? null;
    }

    public function addEnum(ClassLike $node, string $name): void
    {
        $this->enumNames[spl_object_id($node)] = $name;
    }

    public static function isStringEnum(ClassLike $node): bool
    {
        return $node instanceof Enum_ && $node->scalarType !== null && $node->scalarType->toLowerString() === 'string';
    }

    /**
     * How messages name a class-like: `class "Pet"`, `enum "PetStatus"`, `an anonymous class`…
     */
    public static function nodeToDescription(ClassLike $node): string
    {
        if ($node->name === null) {
            return 'an anonymous class';
        }

        return sprintf('%s "%s"', self::nodeToKind($node), $node->name->toString());
    }

    public static function nodeToKind(ClassLike $node): string
    {
        if ($node instanceof Enum_) {
            return 'enum';
        }
        if ($node instanceof Interface_) {
            return 'interface';
        }
        if ($node instanceof Trait_) {
            return 'trait';
        }

        return 'class';
    }

    /**
     * @return NamedSchema|list<Diagnostic>
     */
    public function enumToSchema(ClassDeclaration $enum): NamedSchema|array
    {
        $id = spl_object_id($enum->node);
        if (isset($this->enumSchemas[$id])) {
            return $this->enumSchemas[$id];
        }
        $name = $this->enumNames[$id] ?? $this->enumToDeclaredName($enum);
        if (is_array($name)) {
            $this->enumSchemas[$id] = $name;

            return $name;
        }
        $this->enumSchemas[$id] = $this->enumToNewSchema($enum, $name ?? SchemaNaming::classToShortName($enum->name));

        return $this->enumSchemas[$id];
    }

    /**
     * The name an enum the project does not read as a schema, like one in vendor/, declares for itself.
     *
     * @return string|list<Diagnostic>|null
     */
    private function enumToDeclaredName(ClassDeclaration $enum): string|array|null
    {
        $this->tags->addFile($enum->file);
        $tag = $this->tags->schemaTag($enum->node);
        if ($tag !== null) {
            $options = TagArgumentsToOptions::argumentsToOptions($tag, $tag->arguments, ['name']);
            if ($options instanceof Diagnostic) {
                return [$options];
            }

            return $options->name;
        }
        foreach (SdkAttributes::elementToSdkAttributes($enum->node, $enum->file) as $attribute) {
            if ($attribute->name !== 'BidirektSchema') {
                continue;
            }
            $arguments = $this->evaluator->argumentsToNamed($attribute->attribute->args, SdkAttributes::classToParameters(BidirektSchema::class), '#[BidirektSchema]', $attribute->location, $enum->file);
            if (is_array($arguments)) {
                return $arguments;
            }
            $name = $arguments->nameArgument('name');
            if ($name instanceof Diagnostic) {
                return [$name];
            }

            return $name;
        }

        return null;
    }

    /**
     * @return list<NamedSchema>
     */
    public function enumSchemas(): array
    {
        $schemas = [];
        foreach ($this->enumSchemas as $schema) {
            if ($schema instanceof NamedSchema) {
                $schemas[] = $schema;
            }
        }

        return $schemas;
    }

    /**
     * @return NamedSchema|list<Diagnostic>
     */
    private function enumToNewSchema(ClassDeclaration $enum, string $name): NamedSchema|array
    {
        $members = [];
        $errors = [];
        foreach ($enum->node->stmts as $statement) {
            if (!$statement instanceof EnumCase || $statement->expr === null) {
                continue;
            }
            $value = $this->evaluator->expressionToValue($statement->expr, $enum->file);
            if (is_array($value)) {
                array_push($errors, ...$value);

                continue;
            }
            if (!is_string($value->value)) {
                $errors[] = new Diagnostic(
                    sprintf('case "%s" of enum "%s" must have a string value: write a string', $statement->name->toString(), SchemaNaming::classToShortName($enum->name)),
                    $value->location,
                );

                continue;
            }
            if (!in_array($value->value, $members, true)) {
                $members[] = $value->value;
            }
        }
        if ($errors !== []) {
            return $errors;
        }

        return new NamedSchema($name, new EnumNode($members), $enum->file->locationOf($enum->node));
    }
}
