<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\Property;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;

/**
 * Reads the classes and enums with `#[BidirektSchema]` into named schemas, their fields being the
 * properties and promoted constructor parameters with `#[BidirektField]`, inherited ones included.
 */
final class ClassesToSchemas
{
    private const PROPERTY_DOCBLOCK = 'in the docblock above the property and its attributes';

    /** @var list<Diagnostic> */
    private array $errors = [];

    /** @var list<Diagnostic> */
    private array $warnings = [];

    /** @var array<string, true> node keys of the class-likes read as a schema, or as a parent or trait of one */
    private array $readClasses = [];

    /** @var list<NamedSchema> the classes whose schema annotation has errors */
    private array $unreadSchemas = [];

    /** @var array<string, Property|null> member node key, with the scope keys when it writes self, static or parent => its property, null when it cannot be read */
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
     * @param list<string> $paths the project files `$files` reads whole
     */
    public static function filesToSchemas(
        array $paths,
        ParsedFiles $files,
        ClassIndex $classes,
        SchemaClasses $schemas,
        SchemaTags $tags,
        ConstantExpressionToValue $evaluator,
        FieldToSchemaNode $fieldTypes,
        FieldsToProperties $declaredFields,
    ): ReadResult {
        $reader = new self($classes, $schemas, $tags, $evaluator, $fieldTypes, $declaredFields);
        $schemaClasses = [];
        foreach ($paths as $path) {
            $schemaClasses[$path] = $reader->fileToSchemaClasses($files->projectFile($path));
        }
        $schemas = $reader->unreadSchemas;
        foreach (array_filter($schemaClasses) as $path => $fileClasses) {
            array_push($schemas, ...$reader->fileToSchemas($files->projectFile($path), $fileClasses));
        }
        foreach ($paths as $path) {
            $reader->fileToFieldWarnings($files->projectFile($path));
        }

        return new ReadResult(
            new Contract([], [], $schemas),
            $reader->errors,
            $reader->warnings,
        );
    }

    /**
     * Registers the schema names of the file's class-likes. Nothing of the file's tree outlives the call, since
     * a file that is not kept is parsed again each time it is read: the classes are found again by their key.
     *
     * @return list<array{string, string, string, ConstantValue|null, AnnotationForm}> node key, class, schema
     *                                                                                 name, `fields:` and annotation
     *                                                                                 of each class whose fields
     *                                                                                 are to be read
     */
    private function fileToSchemaClasses(ParsedFile $file): array
    {
        $schemaClasses = [];
        foreach ((new NodeFinder())->findInstanceOf($file->statements, ClassLike::class) as $node) {
            $schemaClass = $this->nodeToSchemaClass($node, $file);
            if ($schemaClass !== null) {
                [$declaration, $name, $fields, $form] = $schemaClass;
                $schemaClasses[] = [$file->nodeToKey($declaration->node), $declaration->name, $name, $fields, $form];
            }
        }

        return $schemaClasses;
    }

    /**
     * @param list<array{string, string, string, ConstantValue|null, AnnotationForm}> $schemaClasses
     * @return list<NamedSchema>
     */
    private function fileToSchemas(ParsedFile $file, array $schemaClasses): array
    {
        $schemas = [];
        foreach ($schemaClasses as [$key, $class, $name, $fields, $form]) {
            $schemas[] = $this->classToSchema(new ClassDeclaration($class, $file->keyToClassLike($key), $file), $name, $fields, $form);
        }

        return $schemas;
    }

    /**
     * Registers the schema name of a class-like with `#[BidirektSchema]` or `@bidirekt schema`.
     *
     * @return array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}|null the class, its schema name,
     *                                                                                  its `fields:` and its annotation,
     *                                                                                  when its fields are to be read
     */
    private function nodeToSchemaClass(ClassLike $node, ParsedFile $file): ?array
    {
        $read = $this->schemas->nodeToAnnotation($node, $file);
        if ($read === null) {
            return null;
        }
        $this->readClasses[$file->nodeToKey($node)] = true;
        [$annotation, $errors] = $read;
        $this->addErrors($errors);
        if ($annotation === null || !$annotation->readable) {
            $this->addUnreadClass($node, $file, $annotation?->declaredName);

            return null;
        }

        return $this->namedToSchemaClass($node, $file, $annotation->declaredName, $annotation->form, $annotation->fields);
    }

    /**
     * Registers the schema name of an annotated class-like: an enum's schema is read at once.
     *
     * @return array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}|null
     */
    private function namedToSchemaClass(ClassLike $node, ParsedFile $file, ?string $declared, AnnotationForm $form, ?ConstantValue $fields): ?array
    {
        $declaration = $this->nodeToDeclaration($node, $file, $declared, $form);
        if ($declaration === null) {
            return null;
        }
        $name = SchemaNaming::schemaToName($declared, $declaration->name);
        if ($node instanceof Enum_) {
            $this->schemas->addEnum($file, $node, $name);
            $schema = $this->schemas->enumToSchema($declaration);
            if (is_array($schema)) {
                $this->addErrors($schema);
                $this->addUnreadClass($node, $file, $declared);
            }

            return null;
        }
        $this->schemas->addClass($file, $node, $name);

        return [$declaration, $name, $fields, $form];
    }

    /**
     * A class or enum whose schema cannot be read is still declared under its name, with no field, so the
     * references to it resolve and add no error of their own.
     *
     * @param string|null $declared the name its annotation declares, when that could be read
     */
    private function addUnreadClass(ClassLike $node, ParsedFile $file, ?string $declared): void
    {
        if ((!$node instanceof Class_ && !$node instanceof Enum_) || $node->namespacedName === null) {
            return;
        }
        $name = SchemaNaming::schemaToName($declared, $node->namespacedName->toString());
        $this->schemas->addClass($file, $node, $name);
        $this->unreadSchemas[] = new NamedSchema($name, new ObjectNode([]), $file->locationOf($node));
    }

    /**
     * The class-like's single declaration, or null after reporting it is declared in more than one file.
     */
    private function nodeToDeclaration(ClassLike $node, ParsedFile $file, ?string $declaredName, AnnotationForm $form): ?ClassDeclaration
    {
        if ($node->namespacedName === null) {
            if ($declaredName === null) {
                $this->addErrors([new Diagnostic(
                    sprintf('cannot name an anonymous class: declare it with %s', $form->declareSchemaName),
                    $file->locationOf($node),
                )]);

                return null;
            }

            return new ClassDeclaration($declaredName, $node, $file);
        }
        $class = $node->namespacedName->toString();
        $errors = $this->classes->duplicateErrors($class);
        if ($errors !== []) {
            $this->addErrors($errors);

            return null;
        }

        return new ClassDeclaration($class, $node, $file);
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
        $location = $declaration->file->locationOf($declaration->node);
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
     * @param array<string, true> $visited node keys
     * @return array{array<string, AnnotatedMember>, bool} member name => member, and whether every parent and trait could be read
     */
    private function classToMembers(ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        $id = $class->file->nodeToKey($class->node);
        if (isset($visited[$id])) {
            return [[], true];
        }
        $visited[$id] = true;
        $this->readClasses[$id] = true;
        $this->tags->addFile($class->file);
        if (!$class->node instanceof Trait_) {
            $scope = new ClassScope($class, $scope->staticClass);
        }
        $members = [];
        $complete = true;
        if ($class->node instanceof Class_ && $class->node->extends !== null) {
            [$members, $complete] = $this->relatedToMembers($class->node->extends, 'parent', $class, $scope, $visited);
        }
        foreach ($class->node->stmts as $statement) {
            [$own, $ownComplete] = $this->statementToMembers($statement, $class, $scope, $visited);
            foreach ($own as $name => $member) {
                $members[$name] = $member;
            }
            $complete = $complete && $ownComplete;
        }

        return [$members, $complete];
    }

    /**
     * @param array<string, true> $visited node keys
     * @return array{array<string, AnnotatedMember>, bool}
     */
    private function statementToMembers(Stmt $statement, ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        if ($statement instanceof TraitUse) {
            $members = [];
            $complete = true;
            foreach ($statement->traits as $trait) {
                [$traitMembers, $traitComplete] = $this->relatedToMembers($trait, 'trait', $class, $scope, $visited);
                $members = array_merge($members, $traitMembers);
                $complete = $complete && $traitComplete;
            }

            return [$members, $complete];
        }
        $members = [];
        if ($statement instanceof Stmt\Property) {
            $attributes = self::attributesNamed($statement, $class->file, 'BidirektField');
            $tag = $this->tags->fieldTag($class->file, $statement);
            if ($attributes === [] && $tag === null) {
                return [[], true];
            }
            if ($statement->isStatic()) {
                $this->addStaticWarning($attributes, $tag);

                return [[], true];
            }
            foreach ($statement->props as $item) {
                $members[$item->name->toString()] = new AnnotatedMember($item, $attributes, $tag, $statement, $class, $scope);
            }
        }
        if ($statement instanceof ClassMethod && $statement->name->toLowerString() === '__construct') {
            foreach ($statement->params as $param) {
                $attributes = self::attributesNamed($param, $class->file, 'BidirektField');
                $tag = $this->tags->fieldTag($class->file, $param);
                if (($attributes !== [] || $tag !== null) && $param->isPromoted() && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                    $members[$param->var->name] = new AnnotatedMember($param, $attributes, $tag, $statement, $class, $scope);
                }
            }
        }

        return [$members, true];
    }

    /**
     * @param 'parent'|'trait' $relation
     * @param array<string, true> $visited node keys
     * @return array{array<string, AnnotatedMember>, bool}
     */
    private function relatedToMembers(Name $name, string $relation, ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        $location = $class->file->locationOf($name);
        $lookup = $this->classes->lookup($name->toString(), $location);
        if ($lookup->errors !== []) {
            $this->addErrors($lookup->errors);

            return [[], false];
        }
        if ($lookup->declaration !== null) {
            return $this->classToMembers($lookup->declaration, $scope, $visited);
        }
        if ($relation === 'parent' && ClassIndex::isBuiltIn($name->toString(), false)) {
            return [[], true];
        }
        $written = $name->getAttribute('originalName');
        if (!$written instanceof Name) {
            $written = $name;
        }
        $this->addErrors([new Diagnostic(
            sprintf(
                'cannot read the %s "%s" of %s: declare it in the project or require its package with composer',
                $relation,
                $written->toString(),
                SchemaClasses::nodeToDescription($class->node),
            ),
            $location,
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
        $owner = $annotated->owner;
        $scope = $annotated->scope;
        $key = $owner->file->nodeToKey($annotated->member);
        if (self::mentionsSpecialClass($annotated->member, $annotated->declaration, $owner->file)) {
            $key .= sprintf(
                '|%s|%s',
                $scope->selfClass->file->nodeToKey($scope->selfClass->node),
                $scope->staticClass->file->nodeToKey($scope->staticClass->node),
            );
        }
        if (!array_key_exists($key, $this->properties)) {
            $this->properties[$key] = $this->memberToNewProperty($annotated->member, $annotated->attributes, $annotated->tag, $annotated->declaration, $owner, $scope);
        }

        return $this->properties[$key];
    }

    /**
     * Whether the member's type, docblocks or attributes write `self`, `static` or `parent`, which name another
     * class for each schema that reads it.
     */
    private static function mentionsSpecialClass(PropertyItem|Param $member, Stmt\Property|ClassMethod $declaration, ParsedFile $file): bool
    {
        $written = $file->textOf($declaration) . $declaration->getDocComment()?->getText();
        if ($member instanceof Param) {
            $written = $file->textOf($member) . $member->getDocComment()?->getText() . $declaration->getDocComment()?->getText();
        }

        return preg_match('/\b(?:self|static|parent)\b/i', $written) === 1;
    }

    /**
     * @param list<SdkAttribute> $attributes
     */
    private function memberToNewProperty(PropertyItem|Param $member, array $attributes, ?CommentTag $tag, Stmt\Property|ClassMethod $declaration, ClassDeclaration $owner, ClassScope $scope): ?Property
    {
        $memberName = SchemaNaming::memberToName($member);
        if ($tag !== null && $attributes !== []) {
            $this->addErrors([new Diagnostic('#[BidirektField] and "@bidirekt field" are both on this property: keep one', $attributes[0]->location)]);

            return null;
        }
        if ($tag !== null) {
            return $this->tagToProperty($member, $memberName, $tag, $declaration, $owner, $scope);
        }
        if (count($attributes) > 1) {
            $this->addErrors([new Diagnostic(sprintf('#[BidirektField] is written more than once on "%s": keep one', $memberName), $attributes[1]->location)]);

            return null;
        }
        $attribute = $attributes[0];
        $arguments = $this->evaluator->withScope($scope)->argumentsToNamed($attribute->attribute->args, SdkAttributes::classToParameters(BidirektField::class), '#[BidirektField]', $attribute->location, $owner->file);
        if (is_array($arguments)) {
            $this->addErrors($arguments);

            return null;
        }
        $options = ArgumentsToFieldOptions::argumentsToFieldOptions($arguments, $arguments->nameArgument('name'));
        if (is_array($options)) {
            $this->addErrors($options);

            return null;
        }
        $location = $owner->file->locationOf($member);
        $node = $this->types->fieldToSchemaNode(self::memberToSource($member, $memberName, $declaration), $arguments, $owner, $scope, $location);
        if (is_array($node)) {
            $this->addErrors($node);

            return null;
        }
        return $options->nodeToProperty($memberName, $node, $location);
    }

    private function tagToProperty(PropertyItem|Param $member, string $memberName, CommentTag $tag, Stmt\Property|ClassMethod $declaration, ClassDeclaration $owner, ClassScope $scope): ?Property
    {
        $options = TagArgumentsToOptions::argumentsToOptions($tag, $tag->arguments, TagArgumentsToOptions::FIELD_KEYS);
        if ($options instanceof Diagnostic) {
            $this->addErrors([$options]);

            return null;
        }
        $location = $owner->file->locationOf($member);
        $source = self::memberToSource($member, $memberName, $declaration);
        $node = $this->types->declaredToSchemaNode($source, $options->declared, $owner, $scope, $location, AnnotationForm::tag());
        if (is_array($node)) {
            $this->addErrors($node);

            return null;
        }
        return $options->nodeToProperty($memberName, $node, $location);
    }

    private function fileToFieldWarnings(ParsedFile $file): void
    {
        $members = (new NodeFinder())->find($file->statements, static fn(Node $node): bool => $node instanceof Stmt\Property || $node instanceof Param);
        foreach ($members as $member) {
            $attributes = self::attributesNamed($member, $file, 'BidirektField');
            $tag = $this->tags->fieldTag($file, $member);
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
            if ($member instanceof Param && !$member->isPromoted()) {
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
            $owner = NodeToEnclosingClass::nodeToEnclosingClass($member);
            if ($owner === null || isset($this->readClasses[$file->nodeToKey($owner)])) {
                continue;
            }
            if ($owner instanceof Trait_) {
                $fix = sprintf('use the trait in a class with %s', $form->schemaAnnotation);
            }
            $this->addWarning(new Diagnostic(
                sprintf(
                    '%s is in %s, which is neither a schema nor the parent of one, so it is not read: %s',
                    $form->fieldAnnotation,
                    SchemaClasses::nodeToDescription($owner),
                    $fix,
                ),
                $location,
            ));
        }
    }

    /**
     * @return list<SdkAttribute>
     */
    private static function attributesNamed(Node $element, ParsedFile $file, string $name): array
    {
        return array_values(array_filter(
            SdkAttributes::elementToSdkAttributes($element, $file),
            static fn(SdkAttribute $attribute): bool => $attribute->name === $name,
        ));
    }

    private static function memberToSource(PropertyItem|Param $member, string $memberName, Stmt\Property|ClassMethod $declaration): FieldSource
    {
        $names = NameContextVisitor::nodeToNameContext($member);
        if ($member instanceof Param && $declaration instanceof ClassMethod) {
            return new FieldSource($memberName, $member->type, $member->getDocComment()?->getText(), $declaration->getDocComment()?->getText(), $names, 'in the docblock above the parameter and its attributes');
        }
        if ($declaration instanceof Stmt\Property) {
            return new FieldSource($memberName, $declaration->type, $declaration->getDocComment()?->getText(), null, $names, self::PROPERTY_DOCBLOCK);
        }

        return new FieldSource($memberName, null, null, null, $names, self::PROPERTY_DOCBLOCK);
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
