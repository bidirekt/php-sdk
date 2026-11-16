<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\MapNode;
use Bidirekt\PhpSdk\Model\SchemaNode;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Model\UnionNode;

/**
 * A field's schema node: the declared `type` when there is one, else its native type, with the PHPDoc
 * read only where PHP does not say the type (an array's items, a property without a native type).
 */
final class FieldToSchemaNode
{
    private PhpDocToWrittenType $docs;

    private TypeToSchemaNode $types;

    private DeclaredTypeToSchemaNode $declared;

    public function __construct(ClassIndex $classes, SchemaClasses $schemas)
    {
        $this->docs = new PhpDocToWrittenType();
        $this->types = new TypeToSchemaNode($classes, $schemas);
        $this->declared = new DeclaredTypeToSchemaNode($this->types);
    }

    /**
     * @param SourceLocation $location the field's, where every error about its type points
     * @return SchemaNode|list<Diagnostic>
     */
    public function fieldToSchemaNode(FieldSource $field, AttributeArguments $arguments, ClassDeclaration $owner, ClassScope $scope, SourceLocation $location): SchemaNode|array
    {
        $declared = $this->declared->argumentsToSchemaNode($arguments, $location);
        if (is_array($declared)) {
            return $declared;
        }

        return $this->declaredToSchemaNode($field, $declared, $owner, $scope, $location, AnnotationForm::attribute());
    }

    /**
     * @param SchemaNode|null $declared the type the annotation declares, null when it declares none
     * @param SourceLocation $location the field's, where every error about its type points
     * @return SchemaNode|list<Diagnostic>
     */
    public function declaredToSchemaNode(FieldSource $field, ?SchemaNode $declared, ClassDeclaration $owner, ClassScope $scope, SourceLocation $location, AnnotationForm $form): SchemaNode|array
    {
        $doc = $this->fieldToDocType($field, $location);
        if ($doc instanceof Diagnostic) {
            return [$doc];
        }
        $native = null;
        if ($field->nativeType !== null) {
            $text = $owner->file->textOf($field->nativeType);
            $native = $this->docs->textToWrittenType($text, $field->names);
            if ($native === null) {
                return [new Diagnostic(sprintf('cannot translate "%s" to a contract type: declare it with %s', $text, $form->declareType), $location)];
            }
        }
        $flags = $native ?? $doc;
        if ($declared !== null && !$declared instanceof UnionNode) {
            $declared->nullable = $flags !== null && TypeToSchemaNode::isNullable($flags->node);

            return $declared;
        }
        $written = $flags;
        $arrayNative = null;
        if ($native !== null && $doc !== null && self::isArray($native)) {
            $written = $doc;
            $arrayNative = $native;
        }
        if ($written === null) {
            if ($declared !== null) {
                return $declared;
            }

            return [new Diagnostic(
                sprintf('cannot read the type of "%s": write its type or declare it with %s', $field->member, $form->declareType),
                $location,
            )];
        }
        $node = $this->types->writtenToSchemaNode(new TypeContext($written, $declared, $scope, $location, $form, $field->docblock));
        if (is_array($node) || $arrayNative === null) {
            return $node;
        }
        if (!$node instanceof ArrayNode && !$node instanceof MapNode) {
            return [new Diagnostic(
                sprintf('cannot translate "%s" with the @var "%s": write the type of its items, as in @var list<T> or array<string, T>', $arrayNative->textOf($arrayNative->node), $written->textOf($written->node)),
                $location,
            )];
        }
        $node->nullable = TypeToSchemaNode::isNullable($arrayNative->node);

        return $node;
    }

    private function fieldToDocType(FieldSource $field, SourceLocation $location): WrittenType|Diagnostic|null
    {
        $var = $this->docs->docToVarType($field->varDoc, $field->member, $field->names, $location);
        $param = $this->docs->docToParamType($field->constructorDoc, $field->member, $field->names, $location);
        if ($var instanceof Diagnostic || $param === null) {
            return $var;
        }
        if ($param instanceof Diagnostic || $var === null) {
            return $param;
        }
        if ($var->typeToResolvedText() !== $param->typeToResolvedText()) {
            return new Diagnostic(
                sprintf('"%s" has a @var "%s" and a @param "%s" that differ: keep one of them', $field->member, $var->textOf($var->node), $param->textOf($param->node)),
                $location,
            );
        }

        return $var;
    }

    private static function isArray(WrittenType $native): bool
    {
        $type = TypeToSchemaNode::withoutNull($native->node);

        return $type !== null && TypeToSchemaNode::isArrayKeyword($type);
    }
}
