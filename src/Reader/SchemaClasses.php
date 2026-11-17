<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektSchema;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\EnumNode;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;

/**
 * The schema name of each class with `#[BidirektSchema]`, and the enum schemas, built once per enum
 * so every field that references one shares it.
 */
final class SchemaClasses
{
    /** @var array<int, string> class summary id => schema name */
    private array $classNames = [];

    /** @var array<int, string> enum summary id => name declared by its #[BidirektSchema] */
    private array $enumNames = [];

    /** @var array<int, NamedSchema|list<Diagnostic>|null> enum summary id => schema, why it cannot be one, or null for an enum without string values */
    private array $enumSchemas = [];

    public function __construct(
        private ConstantExpressionToValue $evaluator,
        private SchemaTags $tags,
    ) {}

    public function addClass(ClassSummary $class, string $name): void
    {
        $this->classNames[spl_object_id($class)] = $name;
    }

    public function classToName(ClassDeclaration $class): ?string
    {
        return $this->classNames[spl_object_id($class->summary)] ?? null;
    }

    public function addEnum(ClassSummary $enum, string $name): void
    {
        $this->enumNames[spl_object_id($enum)] = $name;
    }

    public static function isStringEnum(ClassSummary $class): bool
    {
        return $class->kind === 'enum' && $class->scalarType === 'string';
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
     * The schema of an enum, or null for one without string values and without a schema annotation.
     *
     * @return NamedSchema|list<Diagnostic>|null
     */
    public function enumToSchema(ClassDeclaration $enum): NamedSchema|array|null
    {
        $id = spl_object_id($enum->summary);
        if (!array_key_exists($id, $this->enumSchemas)) {
            $this->enumSchemas[$id] = $this->enumToReadSchema($enum, $this->enumNames[$id] ?? null);
        }

        return $this->enumSchemas[$id];
    }

    /**
     * @param string|null $name the schema name the project read for it, null for an enum it did not read,
     *                          like one in vendor/, whose annotation is read here
     * @return NamedSchema|list<Diagnostic>|null
     */
    private function enumToReadSchema(ClassDeclaration $enum, ?string $name): NamedSchema|array|null
    {
        if ($name === null) {
            $read = $this->classToAnnotation($enum->summary, $enum->file);
            if ($read !== null) {
                [$annotation, $errors] = $read;
                if ($errors !== []) {
                    return $errors;
                }
                $name = $annotation?->declaredName;
            }
        }
        if (!self::isStringEnum($enum->summary)) {
            return null;
        }

        return $this->enumToNewSchema($enum, $name ?? SchemaNaming::classToShortName($enum->name));
    }

    /**
     * Reads the `#[BidirektSchema]` or `@bidirekt schema` of a class-like, null when it has neither.
     *
     * @return array{SchemaAnnotation|null, list<Diagnostic>}|null what the annotation declares, null when
     *                                                             the errors keep it from being read
     */
    public function classToAnnotation(ClassSummary $class, FileSummary $file): ?array
    {
        $this->tags->addFile($file);
        $attributes = array_values(array_filter(
            $class->attributes,
            static fn(SdkAttribute $attribute): bool => $attribute->name === 'BidirektSchema',
        ));
        $tag = $file->tags->schemaTag($class->key);
        if ($attributes === [] && $tag === null) {
            return null;
        }
        if ($tag !== null) {
            $errors = [];
            foreach ($attributes as $attribute) {
                $errors[] = new Diagnostic(
                    sprintf('#[BidirektSchema] and "@bidirekt schema" are both on this %s: keep one', $class->kind),
                    $attribute->location,
                );
            }
            [$annotation, $tagErrors] = $this->tagToAnnotation($class, $tag);

            return [$annotation, [...$errors, ...$tagErrors]];
        }
        $description = $class->description;
        if (count($attributes) > 1) {
            return [null, [new Diagnostic(sprintf('#[BidirektSchema] is written more than once on %s: keep one', $description), $attributes[1]->location)]];
        }
        if ($class->kind === 'interface' || $class->kind === 'trait') {
            return [null, [new Diagnostic(
                sprintf('#[BidirektSchema] is on %s, which cannot be a schema: put it on a class or on an enum with string values', $description),
                $class->location,
            )]];
        }
        $attribute = $attributes[0];
        $arguments = $this->evaluator->argumentsToNamedAndErrors($attribute->arguments, SdkAttributes::classToParameters(BidirektSchema::class), '#[BidirektSchema]', $attribute->location);
        $errors = $arguments->errors;
        if (isset($arguments->values['fields']) && $class->kind === 'enum') {
            return [null, [...$errors, new Diagnostic(
                sprintf('"fields" is declared on %s, whose members are its case values: remove "fields"', $description),
                $arguments->values['fields']->location,
            )]];
        }
        if ($class->kind === 'enum') {
            $error = self::enumToUnreadable($class, '#[BidirektSchema] is on', $class->location);
            if ($error !== null) {
                return [null, [...$errors, $error]];
            }
        }
        $declared = $arguments->nameArgument('name');
        if ($declared instanceof Diagnostic) {
            return [null, [...$errors, $declared]];
        }

        return [new SchemaAnnotation($declared, AnnotationForm::attribute(), $arguments->values['fields'] ?? null, $errors === []), $errors];
    }

    /**
     * @return array{SchemaAnnotation|null, list<Diagnostic>}
     */
    private function tagToAnnotation(ClassSummary $class, CommentTag $tag): array
    {
        if ($class->kind === 'interface' || $class->kind === 'trait') {
            return [null, [new Diagnostic(
                sprintf('"@bidirekt schema" is above %s, which cannot be a schema: put it above a class or an enum with string values', $class->description),
                $tag->location,
            )]];
        }
        $options = TagArgumentsToOptions::argumentsToOptions($tag, $tag->arguments, ['name']);
        if ($options instanceof Diagnostic) {
            return [null, [$options]];
        }
        if ($class->kind === 'enum') {
            $error = self::enumToUnreadable($class, '"@bidirekt schema" is above', $tag->location);
            if ($error !== null) {
                return [null, [$error]];
            }
        }

        return [new SchemaAnnotation($options->name, AnnotationForm::tag(), null), []];
    }

    /**
     * Why an annotated enum cannot be a schema, or null when its values are strings.
     *
     * @param string $placed how messages say where the annotation is: `#[BidirektSchema] is on`
     */
    private static function enumToUnreadable(ClassSummary $enum, string $placed, SourceLocation $location): ?Diagnostic
    {
        if ($enum->scalarType === null) {
            return new Diagnostic(sprintf('%s %s, which has no values: give its cases string values', $placed, $enum->description), $location);
        }
        if (!self::isStringEnum($enum)) {
            return new Diagnostic(sprintf('%s %s, whose values are not strings: give its cases string values', $placed, $enum->description), $location);
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
        foreach ($enum->summary->cases as $case) {
            if ($case->value === null) {
                continue;
            }
            $value = $this->evaluator->expressionToValue($case->value);
            if (is_array($value)) {
                array_push($errors, ...$value);

                continue;
            }
            if (!is_string($value->value)) {
                $errors[] = new Diagnostic(
                    sprintf('case "%s" of enum "%s" must have a string value: write a string', $case->name, SchemaNaming::classToShortName($enum->name)),
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

        return new NamedSchema($name, new EnumNode($members), $enum->summary->location);
    }
}
