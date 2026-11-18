<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\Property;

/**
 * Reads the classes and enums with `#[BidirektSchema]` into named schemas, their fields being the
 * properties and promoted constructor parameters with `#[BidirektField]`, inherited ones included.
 */
final class ClassesToSchemas
{
    /** @var list<Diagnostic> */
    private array $errors = [];

    /** @var list<Diagnostic> */
    private array $warnings = [];

    /** @var array<int, true> summary ids, which a summary keeps for the whole read, of the class-likes read as a schema, or as a parent or trait of one */
    private array $readClasses = [];

    /** @var list<NamedSchema> the classes whose schema annotation has errors */
    private array $unreadSchemas = [];

    /** @var array<int|string, Property|null> member summary id, with the scope's class ids when it writes self, static or parent => its property, null when it cannot be read */
    private array $properties = [];

    private function __construct(
        private ClassIndex $classes,
        private SchemaClasses $schemas,
        private SchemaTags $tags,
        private ConstantExpressionToValue $evaluator,
        private FieldToSchemaNode $types,
        private FieldsToProperties $declaredFields,
    ) {}

    /**
     * The enum schemas are left in `$schemas`, to be taken once every reference to them is read.
     *
     * @param list<FileSummary> $files the project files that mention the SDK
     */
    public static function filesToSchemas(
        array $files,
        ClassIndex $classes,
        SchemaClasses $schemas,
        SchemaTags $tags,
        ConstantExpressionToValue $evaluator,
        FieldToSchemaNode $fieldTypes,
        FieldsToProperties $declaredFields,
    ): ReadResult {
        $reader = new self($classes, $schemas, $tags, $evaluator, $fieldTypes, $declaredFields);
        $schemaClasses = [];
        foreach ($files as $file) {
            array_push($schemaClasses, ...$reader->fileToSchemaClasses($file));
        }
        $schemas = $reader->unreadSchemas;
        foreach ($schemaClasses as [$declaration, $name, $fields, $form]) {
            $schemas[] = $reader->classToSchema($declaration, $name, $fields, $form);
        }
        foreach ($files as $file) {
            $reader->fileToFieldWarnings($file);
        }

        return new ReadResult(
            new Contract([], [], $schemas),
            $reader->errors,
            $reader->warnings,
        );
    }

    /**
     * Registers the schema names of the file's class-likes, so every reference resolves before a field is read.
     *
     * @return list<array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}> the class, its schema
     *                                                                                  name, its `fields:` and its
     *                                                                                  annotation, for each class
     *                                                                                  whose fields are to be read
     */
    private function fileToSchemaClasses(FileSummary $file): array
    {
        $schemaClasses = [];
        foreach ($file->classes as $class) {
            $schemaClass = $this->classToSchemaClass($class, $file);
            if ($schemaClass !== null) {
                $schemaClasses[] = $schemaClass;
            }
        }

        return $schemaClasses;
    }

    /**
     * Registers the schema name of a class-like with `#[BidirektSchema]` or `@bidirekt schema`.
     *
     * @return array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}|null the class, its schema name,
     *                                                                                  its `fields:` and its annotation,
     *                                                                                  when its fields are to be read
     */
    private function classToSchemaClass(ClassSummary $class, FileSummary $file): ?array
    {
        if (self::classToFirstDeclaration($class, $file) !== $class) {
            return null;
        }
        $read = $this->schemas->classToAnnotation($class, $file);
        if ($read === null) {
            if ($class->unimportedSchema) {
                $this->readClasses[spl_object_id($class)] = true;
                $this->addUnreadClass($class, null);
            }

            return null;
        }
        $this->readClasses[spl_object_id($class)] = true;
        [$annotation, $errors] = $read;
        $this->addErrors($errors);
        if ($annotation === null || !$annotation->readable) {
            $this->addUnreadClass($class, $annotation?->declaredName);

            return null;
        }

        return $this->namedToSchemaClass($class, $file, $annotation->declaredName, $annotation->form, $annotation->fields);
    }

    /**
     * Registers the schema name of an annotated class-like: an enum's schema is read at once.
     *
     * @return array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}|null
     */
    private function namedToSchemaClass(ClassSummary $class, FileSummary $file, ?string $declared, AnnotationForm $form, ?ConstantValue $fields): ?array
    {
        $declaration = $this->classToDeclaration($class, $file, $declared, $form);
        if ($declaration === null) {
            return null;
        }
        $name = SchemaNaming::schemaToName($declared, $declaration->name);
        if ($class->kind === 'enum') {
            $this->schemas->addEnum($class, $name);
            $schema = $this->schemas->enumToSchema($declaration);
            if (is_array($schema)) {
                $this->addErrors($schema);
                $this->addUnreadClass($class, $declared);
            }

            return null;
        }
        $this->schemas->addClass($class, $name);

        return [$declaration, $name, $fields, $form];
    }

    /**
     * The class-like the file declares first under the same name, which PHP and the reader take for a class
     * declared again in the same file, e.g. in the two branches of an `if`.
     */
    private static function classToFirstDeclaration(ClassSummary $class, FileSummary $file): ClassSummary
    {
        if ($class->name === null) {
            return $class;
        }

        return $file->classDeclarationNamed(strtolower($class->name))->summary ?? $class;
    }

    /**
     * A class or enum whose schema cannot be read is still declared under its name, with no field, so the
     * references to it resolve and add no error of their own.
     *
     * @param string|null $declared the name its annotation declares, when that could be read
     */
    private function addUnreadClass(ClassSummary $class, ?string $declared): void
    {
        if (($class->kind !== 'class' && $class->kind !== 'enum') || $class->name === null) {
            return;
        }
        $name = SchemaNaming::schemaToName($declared, $class->name);
        $this->schemas->addClass($class, $name);
        $this->unreadSchemas[] = new NamedSchema($name, new ObjectNode([]), $class->location);
    }

    /**
     * The class-like's single declaration, or null after reporting it is declared in more than one file.
     */
    private function classToDeclaration(ClassSummary $class, FileSummary $file, ?string $declaredName, AnnotationForm $form): ?ClassDeclaration
    {
        if ($class->name === null) {
            if ($declaredName === null) {
                $this->addErrors([new Diagnostic(
                    sprintf('cannot name an anonymous class: declare it with %s', $form->declareSchemaName),
                    $class->location,
                )]);

                return null;
            }

            return new ClassDeclaration($declaredName, $class, $file);
        }
        $errors = $this->classes->duplicateErrors($class->name);
        if ($errors !== []) {
            $this->addErrors($errors);

            return null;
        }

        return new ClassDeclaration($class->name, $class, $file);
    }

    /**
     * A schema with no field is still declared, so the references to it add no error of their own.
     *
     * @param ConstantValue|null $declaredFields the `fields:` of its #[BidirektSchema], which come after the annotated ones
     */
    private function classToSchema(ClassDeclaration $declaration, string $name, ?ConstantValue $declaredFields, AnnotationForm $form): NamedSchema
    {
        [$members, $complete] = $this->classToMembers($declaration, new ClassScope($declaration, $declaration), []);
        $properties = [];
        foreach ($members as $member) {
            $property = $this->memberToProperty($member);
            if ($property === null) {
                $complete = false;

                continue;
            }
            $properties[] = $property;
        }
        $fix = sprintf('annotate at least one field with %s', $form->fieldAnnotation);
        if ($declaredFields !== null) {
            [$declared, $errors] = $this->declaredFields->fieldsToProperties($declaredFields);
            $this->addErrors($errors);
            array_push($properties, ...$declared);
            $complete = $complete && $errors === [];
            $fix = 'declare at least one field in "fields"';
        }
        $location = $declaration->summary->location;
        if ($properties === [] && $complete) {
            $this->addErrors([new Diagnostic(sprintf('schema "%s" has no field: %s', $name, $fix), $location)]);
        }

        return new NamedSchema($name, new ObjectNode($properties), $location);
    }

    /**
     * The annotated members in contract order: the parent's first, then the class's own in source order, a
     * trait's at its `use` and the promoted parameters at the constructor; a redeclared member takes the place
     * of the parent's, which is not translated.
     *
     * @param ClassScope $scope its `self` is the class using a trait, which a class read replaces with itself
     * @param array<int, true> $visited summary ids
     * @return array{array<string, AnnotatedMember>, bool} member name => member, and whether every parent and trait could be read
     */
    private function classToMembers(ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        $id = spl_object_id($class->summary);
        if (isset($visited[$id])) {
            return [[], true];
        }
        $visited[$id] = true;
        $this->readClasses[$id] = true;
        $this->tags->addFile($class->file);
        if ($class->summary->kind !== 'trait') {
            $scope = new ClassScope($class, $scope->staticClass);
        }
        $members = [];
        $complete = true;
        if ($class->summary->parent !== null) {
            [$members, $complete] = $this->relatedToMembers($class->summary->parent, 'parent', $class, $scope, $visited);
        }
        foreach ($class->summary->members as $declaration) {
            [$own, $ownComplete] = $this->declarationToMembers($declaration, $class, $scope, $visited);
            foreach ($own as $name => $member) {
                $members[$name] = $member;
            }
            $complete = $complete && $ownComplete;
        }

        return [$members, $complete];
    }

    /**
     * @param array<int, true> $visited summary ids
     * @return array{array<string, AnnotatedMember>, bool}
     */
    private function declarationToMembers(TraitUseSummary|MemberDeclaration $declaration, ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        if ($declaration instanceof TraitUseSummary) {
            $members = [];
            $complete = true;
            foreach ($declaration->traits as $trait) {
                [$traitMembers, $traitComplete] = $this->relatedToMembers($trait, 'trait', $class, $scope, $visited);
                $members = array_merge($members, $traitMembers);
                $complete = $complete && $traitComplete;
            }

            return [$members, $complete];
        }
        $attributes = self::fieldAttributes($declaration->attributes);
        $tag = $class->file->tags->fieldTag($declaration->key);
        if ($attributes === [] && $tag === null) {
            return [[], !$declaration->unimportedField];
        }
        if ($declaration->static) {
            $this->addStaticWarning($attributes, $tag);

            return [[], true];
        }
        $members = [];
        foreach ($declaration->members as $member) {
            $members[$member->name] = new AnnotatedMember($member, $attributes, $tag, $scope);
        }

        return [$members, true];
    }

    /**
     * @param 'parent'|'trait' $relation
     * @param array<int, true> $visited summary ids
     * @return array{array<string, AnnotatedMember>, bool}
     */
    private function relatedToMembers(NameSummary $name, string $relation, ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        $lookup = $this->classes->lookup($name->resolved, $name->location);
        if ($lookup->errors !== []) {
            $this->addErrors($lookup->errors);

            return [[], false];
        }
        if ($lookup->declaration !== null) {
            return $this->classToMembers($lookup->declaration, $scope, $visited);
        }
        if ($relation === 'parent' && ClassIndex::isBuiltIn($name->resolved, false)) {
            return [[], true];
        }
        $this->addErrors([new Diagnostic(
            sprintf(
                'cannot read the %s "%s" of %s: %s',
                $relation,
                $name->written,
                $class->summary->description,
                $this->classes->missingClassFix(),
            ),
            $name->location,
        )]);

        return [[], false];
    }

    /**
     * @param list<SdkAttribute> $attributes
     */
    private function addStaticWarning(array $attributes, ?CommentTag $tag): void
    {
        if ($tag !== null) {
            $this->addWarning(new Diagnostic('"@bidirekt field" is above a static property, so it is not read: put it above a property', $tag->location));

            return;
        }
        $this->addWarning(new Diagnostic('#[BidirektField] is on a static property, so it is not read: put it on a property', $attributes[0]->location));
    }

    private function memberToProperty(AnnotatedMember $annotated): ?Property
    {
        $scope = $annotated->scope;
        $key = spl_object_id($annotated->member);
        if ($annotated->member->mentionsSpecialClass) {
            $key .= sprintf('|%d|%d', spl_object_id($scope->selfClass->summary), spl_object_id($scope->staticClass->summary));
        }
        if (!array_key_exists($key, $this->properties)) {
            $this->properties[$key] = $this->memberToNewProperty($annotated->member, $annotated->attributes, $annotated->tag, $scope);
        }

        return $this->properties[$key];
    }

    /**
     * @param list<SdkAttribute> $attributes
     */
    private function memberToNewProperty(MemberSummary $member, array $attributes, ?CommentTag $tag, ClassScope $scope): ?Property
    {
        if ($tag !== null && $attributes !== []) {
            $this->addErrors([new Diagnostic('#[BidirektField] and "@bidirekt field" are both on this property: keep one', $attributes[0]->location)]);

            return null;
        }
        if ($tag !== null) {
            return $this->tagToProperty($member, $tag, $scope);
        }
        if (count($attributes) > 1) {
            $this->addErrors([new Diagnostic(sprintf('#[BidirektField] is written more than once on "%s": keep one', $member->name), $attributes[1]->location)]);

            return null;
        }
        $attribute = $attributes[0];
        $arguments = $this->evaluator->withScope($scope)->argumentsToNamed($attribute->arguments, SdkAttributes::classToParameters(BidirektField::class), '#[BidirektField]', $attribute->location);
        if (is_array($arguments)) {
            $this->addErrors($arguments);

            return null;
        }
        $options = ArgumentsToFieldOptions::argumentsToFieldOptions($arguments, $arguments->nameArgument('name'));
        if (is_array($options)) {
            $this->addErrors($options);

            return null;
        }
        $node = $this->types->fieldToSchemaNode($member->source, $arguments, $scope, $member->location);
        if (is_array($node)) {
            $this->addErrors($node);

            return null;
        }

        return $options->nodeToProperty($member->name, $node, $member->location);
    }

    private function tagToProperty(MemberSummary $member, CommentTag $tag, ClassScope $scope): ?Property
    {
        $options = TagArgumentsToOptions::argumentsToOptions($tag, $tag->arguments, TagArgumentsToOptions::FIELD_KEYS);
        if ($options instanceof Diagnostic) {
            $this->addErrors([$options]);

            return null;
        }
        $node = $this->types->declaredToSchemaNode($member->source, $options->declared, $scope, $member->location, AnnotationForm::tag());
        if (is_array($node)) {
            $this->addErrors($node);

            return null;
        }

        return $options->nodeToProperty($member->name, $node, $member->location);
    }

    private function fileToFieldWarnings(FileSummary $file): void
    {
        foreach ($file->fieldSites as $site) {
            $attributes = self::fieldAttributes($site->attributes);
            $tag = $file->tags->fieldTag($site->key);
            if ($attributes === [] && $tag === null) {
                continue;
            }
            $form = AnnotationForm::attribute();
            $placed = 'on';
            $location = $attributes[0]->location ?? null;
            $fix = 'add #[BidirektSchema] to the class';
            if ($tag !== null) {
                $form = AnnotationForm::tag();
                $placed = 'above';
                $location = $tag->location;
                $fix = $form->addSchema;
            }
            if ($site->notPromotedParameter) {
                $this->addWarning(new Diagnostic(
                    sprintf(
                        '%s is %s a parameter that is not promoted, so it is not read: put it %s a property or a promoted constructor parameter',
                        $form->fieldAnnotation,
                        $placed,
                        $placed,
                    ),
                    $location,
                ));

                continue;
            }
            $owner = $site->owner;
            if ($owner === null || isset($this->readClasses[spl_object_id(self::classToFirstDeclaration($owner, $file))])) {
                continue;
            }
            if ($owner->kind === 'trait') {
                $fix = sprintf('use the trait in a class with %s', $form->schemaAnnotation);
            }
            $this->addWarning(new Diagnostic(
                sprintf(
                    '%s is in %s, which is neither a schema nor the parent of one, so it is not read: %s',
                    $form->fieldAnnotation,
                    $owner->description,
                    $fix,
                ),
                $location,
            ));
        }
    }

    /**
     * @param list<SdkAttribute> $attributes
     * @return list<SdkAttribute> the #[BidirektField] among them
     */
    private static function fieldAttributes(array $attributes): array
    {
        return array_values(array_filter(
            $attributes,
            static fn(SdkAttribute $attribute): bool => $attribute->name === 'BidirektField',
        ));
    }

    /**
     * @param list<Diagnostic> $errors
     */
    private function addErrors(array $errors): void
    {
        array_push($this->errors, ...$errors);
    }

    private function addWarning(Diagnostic $warning): void
    {
        $this->warnings[] = $warning;
    }
}
