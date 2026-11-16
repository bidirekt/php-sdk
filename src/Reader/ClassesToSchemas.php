<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\Property;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeFinder;

/**
 * Reads the classes and enums with `#[BidirektSchema]` into named schemas, their fields being the
 * properties and promoted constructor parameters with `#[BidirektField]`, inherited ones included.
 */
final class ClassesToSchemas
{
    private ConstantExpressionToValue $evaluator;

    private FieldToSchemaNode $types;

    private FieldsToProperties $declaredFields;

    /** @var list<Diagnostic> */
    private array $errors = [];

    /** @var list<Diagnostic> */
    private array $warnings = [];

    /** @var array<int, true> node ids of the class-likes read as a schema, or as a parent or trait of one */
    private array $readClasses = [];

    /** @var list<NamedSchema> the classes whose schema annotation has errors */
    private array $unreadSchemas = [];

    /** @var array<int|string, Property|null> member node id, with the scope ids when it writes self or static => its property, null when it cannot be read */
    private array $properties = [];

    private function __construct(
        private ClassIndex $classes,
        private SchemaClasses $schemas,
        private SchemaTags $tags,
    ) {
        $this->evaluator = new ConstantExpressionToValue($classes);
        $this->types = new FieldToSchemaNode($classes, $schemas);
        $this->declaredFields = new FieldsToProperties(new DeclaredTypeToSchemaNode(new TypeToSchemaNode($classes, $schemas)));
    }

    /**
     * The enum schemas are left in `$schemas`, to be taken once every reference to them is read.
     *
     * @param list<ParsedFile> $files the project files
     */
    public static function filesToSchemas(array $files, ClassIndex $classes, SchemaClasses $schemas, SchemaTags $tags): ReadResult
    {
        $reader = new self($classes, $schemas, $tags);
        $schemaClasses = [];
        foreach ($files as $file) {
            foreach ((new NodeFinder())->findInstanceOf($file->statements, ClassLike::class) as $node) {
                $schemaClass = $reader->nodeToSchemaClass($node, $file);
                if ($schemaClass !== null) {
                    $schemaClasses[] = $schemaClass;
                }
            }
        }
        $schemas = $reader->unreadSchemas;
        foreach ($schemaClasses as [$declaration, $name, $fields, $form]) {
            $schema = $reader->classToSchema($declaration, $name, $fields, $form);
            if ($schema !== null) {
                $schemas[] = $schema;
            }
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
     * Registers the schema name of a class-like with `#[BidirektSchema]` or `@bidirekt schema`.
     *
     * @return array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}|null the class, its schema name,
     *                                                                                  its `fields:` and its annotation,
     *                                                                                  when its fields are to be read
     */
    private function nodeToSchemaClass(ClassLike $node, ParsedFile $file): ?array
    {
        $attributes = self::attributesNamed($node, $file, 'BidirektSchema');
        $tag = $this->tags->schemaTag($node);
        if ($attributes === [] && $tag === null) {
            return null;
        }
        $this->readClasses[spl_object_id($node)] = true;
        if ($tag !== null) {
            foreach ($attributes as $attribute) {
                $this->addErrors([new Diagnostic(
                    sprintf('#[BidirektSchema] and "@bidirekt schema" are both on this %s: keep one', SchemaClasses::nodeToKind($node)),
                    $attribute->location,
                )]);
            }

            return $this->tagToSchemaClass($node, $file, $tag);
        }
        $description = SchemaClasses::nodeToDescription($node);
        if (count($attributes) > 1) {
            $this->addErrors([new Diagnostic(sprintf('#[BidirektSchema] is written more than once on %s: keep one', $description), $attributes[1]->location)]);
            $this->addUnreadClass($node, $file);

            return null;
        }
        if ($node instanceof Interface_ || $node instanceof Trait_) {
            $this->addErrors([new Diagnostic(
                sprintf('#[BidirektSchema] is on %s, which cannot be a schema: put it on a class or on an enum with string values', $description),
                $file->locationOf($node),
            )]);

            return null;
        }
        $attribute = $attributes[0];
        $arguments = $this->evaluator->argumentsToNamed($attribute->attribute->args, SdkAttributes::classToParameters(BidirektSchema::class), '#[BidirektSchema]', $attribute->location, $file);
        if (is_array($arguments)) {
            $this->addErrors($arguments);
            $this->addUnreadClass($node, $file);

            return null;
        }
        if (isset($arguments->values['fields']) && $node instanceof Enum_) {
            $this->addErrors([new Diagnostic(
                sprintf('"fields" is declared on %s, whose members are its case values: remove "fields"', $description),
                $arguments->values['fields']->location,
            )]);

            return null;
        }
        if ($node instanceof Enum_ && !$this->isEnumSchemaReadable($node, '#[BidirektSchema] is on', $file->locationOf($node))) {
            return null;
        }
        $declared = $arguments->nameArgument('name');
        if ($declared instanceof Diagnostic) {
            $this->addErrors([$declared]);
            $this->addUnreadClass($node, $file);

            return null;
        }

        return $this->namedToSchemaClass($node, $file, $declared, AnnotationForm::attribute(), $arguments->values['fields'] ?? null);
    }

    /**
     * @return array{ClassDeclaration, string, ConstantValue|null, AnnotationForm}|null
     */
    private function tagToSchemaClass(ClassLike $node, ParsedFile $file, CommentTag $tag): ?array
    {
        if ($node instanceof Interface_ || $node instanceof Trait_) {
            $this->addErrors([new Diagnostic(
                sprintf('"@bidirekt schema" is above %s, which cannot be a schema: put it above a class or an enum with string values', SchemaClasses::nodeToDescription($node)),
                $tag->location,
            )]);

            return null;
        }
        $options = TagArgumentsToOptions::argumentsToOptions($tag, $tag->arguments, ['name']);
        if ($options instanceof Diagnostic) {
            $this->addErrors([$options]);
            $this->addUnreadClass($node, $file);

            return null;
        }
        if ($node instanceof Enum_ && !$this->isEnumSchemaReadable($node, '"@bidirekt schema" is above', $tag->location)) {
            return null;
        }

        return $this->namedToSchemaClass($node, $file, $options->name, AnnotationForm::tag(), null);
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
            $this->schemas->addEnum($node, $name);
            $schema = $this->schemas->enumToSchema($declaration);
            if (is_array($schema)) {
                $this->addErrors($schema);
            }

            return null;
        }
        $this->schemas->addClass($node, $name);

        return [$declaration, $name, $fields, $form];
    }

    /**
     * A class whose schema annotation has errors is still declared under its class name, so the references
     * to it resolve and add no error of their own.
     */
    private function addUnreadClass(ClassLike $node, ParsedFile $file): void
    {
        if (!$node instanceof Class_ || $node->namespacedName === null) {
            return;
        }
        $name = SchemaNaming::classToShortName($node->namespacedName->toString());
        $this->schemas->addClass($node, $name);
        $this->unreadSchemas[] = new NamedSchema($name, new ObjectNode([]), $file->locationOf($node));
    }

    /**
     * @param string $placed how messages say where the annotation is: `#[BidirektSchema] is on`
     */
    private function isEnumSchemaReadable(Enum_ $node, string $placed, SourceLocation $location): bool
    {
        $description = SchemaClasses::nodeToDescription($node);
        if ($node->scalarType === null) {
            $this->addErrors([new Diagnostic(sprintf('%s %s, which has no values: give its cases string values', $placed, $description), $location)]);

            return false;
        }
        if (!SchemaClasses::isStringEnum($node)) {
            $this->addErrors([new Diagnostic(sprintf('%s %s, whose values are not strings: give its cases string values', $placed, $description), $location)]);

            return false;
        }

        return true;
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
        $lookup = $this->classes->lookup($class, $file->locationOf($node));
        if ($lookup->declaration === null) {
            $this->addErrors($lookup->errors);

            return null;
        }

        return $lookup->declaration;
    }

    /**
     * @param ConstantValue|null $declaredFields the `fields:` of its #[BidirektSchema], which come after the annotated ones
     */
    private function classToSchema(ClassDeclaration $declaration, string $name, ?ConstantValue $declaredFields, AnnotationForm $form): ?NamedSchema
    {
        [$fields, $complete] = $this->classToFields($declaration, new ClassScope($declaration, $declaration), []);
        $properties = [];
        foreach ($fields as $property) {
            if ($property !== null) {
                $properties[] = $property;
            }
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

            return null;
        }

        return new NamedSchema($name, new ObjectNode($properties), $location);
    }

    /**
     * The fields in contract order: the parent's first, then the class's own in source order, a trait's
     * at its `use` and the promoted parameters at the constructor; a redeclared field takes the place of
     * the parent's.
     *
     * @param ClassScope $scope its `self` is the class using a trait, which a class read replaces with itself
     * @param array<int, true> $visited
     * @return array{array<string, Property|null>, bool} member name => property (null when it cannot be read),
     *                                                   and whether every annotated field and parent could be read
     */
    private function classToFields(ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        $id = spl_object_id($class->node);
        if (isset($visited[$id])) {
            return [[], true];
        }
        $visited[$id] = true;
        $this->readClasses[$id] = true;
        $this->tags->addFile($class->file);
        if (!$class->node instanceof Trait_) {
            $scope = new ClassScope($class, $scope->staticClass);
        }
        $fields = [];
        $complete = true;
        if ($class->node instanceof Class_ && $class->node->extends !== null) {
            [$fields, $complete] = $this->relatedToFields($class->node->extends, 'parent', $class, $scope, $visited);
        }
        foreach ($class->node->stmts as $statement) {
            [$own, $ownComplete] = $this->statementToFields($statement, $class, $scope, $visited);
            foreach ($own as $member => $property) {
                $fields[$member] = $property;
            }
            $complete = $complete && $ownComplete;
        }
        foreach ($fields as $property) {
            $complete = $complete && $property !== null;
        }

        return [$fields, $complete];
    }

    /**
     * @param array<int, true> $visited
     * @return array{array<string, Property|null>, bool}
     */
    private function statementToFields(Stmt $statement, ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        if ($statement instanceof TraitUse) {
            $fields = [];
            $complete = true;
            foreach ($statement->traits as $trait) {
                [$traitFields, $traitComplete] = $this->relatedToFields($trait, 'trait', $class, $scope, $visited);
                $fields = array_merge($fields, $traitFields);
                $complete = $complete && $traitComplete;
            }

            return [$fields, $complete];
        }
        $fields = [];
        if ($statement instanceof Stmt\Property) {
            $attributes = self::attributesNamed($statement, $class->file, 'BidirektField');
            $tag = $this->tags->fieldTag($statement);
            if ($attributes === [] && $tag === null) {
                return [[], true];
            }
            if ($statement->isStatic()) {
                $this->addStaticWarning($attributes, $tag);

                return [[], true];
            }
            foreach ($statement->props as $item) {
                $fields[$item->name->toString()] = $this->memberToProperty($item, $attributes, $tag, $statement, $class, $scope);
            }
        }
        if ($statement instanceof ClassMethod && $statement->name->toLowerString() === '__construct') {
            foreach ($statement->params as $param) {
                $attributes = self::attributesNamed($param, $class->file, 'BidirektField');
                $tag = $this->tags->fieldTag($param);
                if (($attributes !== [] || $tag !== null) && $param->isPromoted() && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                    $fields[$param->var->name] = $this->memberToProperty($param, $attributes, $tag, $statement, $class, $scope);
                }
            }
        }

        return [$fields, true];
    }

    /**
     * @param 'parent'|'trait' $relation
     * @param array<int, true> $visited
     * @return array{array<string, Property|null>, bool}
     */
    private function relatedToFields(Name $name, string $relation, ClassDeclaration $class, ClassScope $scope, array $visited): array
    {
        $location = $class->file->locationOf($name);
        $lookup = $this->classes->lookup($name->toString(), $location);
        if ($lookup->errors !== []) {
            $this->addErrors($lookup->errors);

            return [[], false];
        }
        if ($lookup->declaration !== null) {
            return $this->classToFields($lookup->declaration, $scope, $visited);
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

    /**
     * @param list<SdkAttribute> $attributes the member's #[BidirektField], one unless written twice
     * @param CommentTag|null $tag the member's `@bidirekt field`
     * @param Stmt\Property|ClassMethod $declaration the property statement, or the constructor of a promoted parameter
     */
    private function memberToProperty(PropertyItem|Param $member, array $attributes, ?CommentTag $tag, Stmt\Property|ClassMethod $declaration, ClassDeclaration $owner, ClassScope $scope): ?Property
    {
        $key = (string) spl_object_id($member);
        if (self::mentionsSelfOrStatic($member, $declaration, $owner->file)) {
            $key .= sprintf(':%d:%d', spl_object_id($scope->selfClass->node), spl_object_id($scope->staticClass->node));
        }
        if (!array_key_exists($key, $this->properties)) {
            $this->properties[$key] = $this->memberToNewProperty($member, $attributes, $tag, $declaration, $owner, $scope);
        }

        return $this->properties[$key];
    }

    /**
     * Whether the member's type, docblocks or attributes write `self` or `static`, which name another class
     * for each schema that reads it.
     */
    private static function mentionsSelfOrStatic(PropertyItem|Param $member, Stmt\Property|ClassMethod $declaration, ParsedFile $file): bool
    {
        $written = $file->textOf($declaration) . $declaration->getDocComment()?->getText();
        if ($member instanceof Param) {
            $written = $file->textOf($member) . $member->getDocComment()?->getText() . $declaration->getDocComment()?->getText();
        }

        return preg_match('/\b(?:self|static)\b/i', $written) === 1;
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
        $options = ArgumentsToFieldOptions::argumentsToFieldOptions($arguments);
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
            $tag = $this->tags->fieldTag($member);
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
            if ($owner === null || isset($this->readClasses[spl_object_id($owner)])) {
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
            return new FieldSource($memberName, $member->type, $member->getDocComment()?->getText(), $declaration->getDocComment()?->getText(), $names);
        }
        if ($declaration instanceof Stmt\Property) {
            return new FieldSource($memberName, $declaration->type, $declaration->getDocComment()?->getText(), null, $names);
        }

        return new FieldSource($memberName, null, null, null, $names);
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
