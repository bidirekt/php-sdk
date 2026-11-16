<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\MapNode;
use Bidirekt\PhpSdk\Model\PrimitiveNode;
use Bidirekt\PhpSdk\Model\RefNode;
use Bidirekt\PhpSdk\Model\SchemaNode;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Model\UnionMember;
use Bidirekt\PhpSdk\Model\UnionNode;

/**
 * Reads the type a field declares with `type`, `items`, `values` and `union`, written with the
 * contract's keys: `type: 'array', items: 'Pet'`, `items: ['type' => 'map', 'values' => 'integer']`.
 */
final class DeclaredTypeToSchemaNode
{
    private const PRIMITIVES = ['string', 'integer', 'float', 'number', 'boolean'];

    private const NESTED_KEYS = ['type', 'items', 'values', 'nullable', 'union'];

    public function __construct(
        private TypeToSchemaNode $types,
    ) {}

    /**
     * @param SourceLocation $location the field's
     * @param string $where how messages name the field's declaration
     * @return SchemaNode|list<Diagnostic>|null null when the field declares neither `type` nor `union`
     */
    public function argumentsToSchemaNode(AttributeArguments $arguments, SourceLocation $location, string $where = '#[BidirektField]'): SchemaNode|array|null
    {
        $values = $arguments->values;
        if (isset($values['type']) && isset($values['union'])) {
            return [new Diagnostic(sprintf('declare either "type" or "union" in %s, not both', $where), $location)];
        }
        if (!isset($values['type']) && !isset($values['union']) && !isset($values['items']) && !isset($values['values'])) {
            return null;
        }

        return $this->partsToNode($values['type'] ?? null, $values['items'] ?? null, $values['values'] ?? null, $values['union'] ?? null, $where, $location);
    }

    /**
     * @param string $where how messages name the place the parts are written
     * @return SchemaNode|list<Diagnostic>
     */
    private function partsToNode(?ConstantValue $type, ?ConstantValue $items, ?ConstantValue $values, ?ConstantValue $union, string $where, SourceLocation $location): SchemaNode|array
    {
        $kind = null;
        if ($type !== null && is_string($type->value)) {
            $kind = $type->value;
        }
        if ($items !== null && $kind !== 'array') {
            return [new Diagnostic('"items" is declared without type: \'array\': write type: \'array\' or remove "items"', $items->location)];
        }
        if ($values !== null && $kind !== 'map') {
            return [new Diagnostic('"values" is declared without type: \'map\': write type: \'map\' or remove "values"', $values->location)];
        }
        if ($union !== null) {
            return $this->unionToNode($union);
        }
        if ($type === null) {
            return [new Diagnostic(sprintf('%s declares no type: write "type" or "union"', $where), $location)];
        }
        if ($kind === 'array') {
            if ($items === null) {
                return [new Diagnostic('type: \'array\' needs "items": write the type of its items, as in items: \'Pet\'', $type->location)];
            }
            $node = $this->nestedToNode($items, 'items');
            if (is_array($node)) {
                return $node;
            }

            return new ArrayNode($node);
        }
        if ($kind === 'map') {
            if ($values === null) {
                return [new Diagnostic('type: \'map\' needs "values": write the type of its values, as in values: \'Pet\'', $type->location)];
            }
            $node = $this->nestedToNode($values, 'values');
            if (is_array($node)) {
                return $node;
            }

            return new MapNode($node);
        }

        return $this->leafToNode($type, 'type');
    }

    /**
     * @param 'items'|'values' $what
     * @return SchemaNode|list<Diagnostic>
     */
    private function nestedToNode(ConstantValue $value, string $what): SchemaNode|array
    {
        if (!$value->value instanceof ConstantArray) {
            return $this->leafToNode($value, $what);
        }
        $parts = [];
        foreach ($value->value->items as $item) {
            $key = $item->key;
            if ($key === null) {
                return [new Diagnostic(
                    sprintf('"%s" has an entry without a key: write its keys among type, items, values, nullable and union', $what),
                    $item->value->location,
                )];
            }
            if (!is_string($key->value) || !in_array($key->value, self::NESTED_KEYS, true)) {
                return [new Diagnostic(
                    sprintf('unknown key %s in "%s": write type, items, values, nullable or union', $key->valueToText(), $what),
                    $key->location,
                )];
            }
            $parts[$key->value] = $item->value;
        }
        if (isset($parts['type']) && isset($parts['union'])) {
            return [new Diagnostic(sprintf('declare either "type" or "union" in "%s", not both', $what), $value->location)];
        }
        $nullable = $parts['nullable'] ?? null;
        if ($nullable !== null && !is_bool($nullable->value)) {
            return [new Diagnostic('"nullable" must be true or false: write true, false or a class constant that holds one', $nullable->location)];
        }
        $node = $this->partsToNode($parts['type'] ?? null, $parts['items'] ?? null, $parts['values'] ?? null, $parts['union'] ?? null, sprintf('"%s"', $what), $value->location);
        if ($node instanceof SchemaNode && $nullable !== null) {
            $node->nullable = (bool) $nullable->value;
        }

        return $node;
    }

    /**
     * A primitive, a schema name or a class.
     *
     * @return SchemaNode|list<Diagnostic>
     */
    private function leafToNode(ConstantValue $value, string $what): SchemaNode|array
    {
        $type = $value->value;
        if ($type instanceof ClassReference) {
            return $this->types->classToSchemaNode($type->class, SchemaNaming::classToShortName($type->class), $value->location, AnnotationForm::attribute());
        }
        if (!is_string($type)) {
            return [new Diagnostic(
                sprintf('"%s" must be a type: write a primitive such as \'string\', a schema name or a class such as Pet::class', $what),
                $value->location,
            )];
        }
        if (in_array($type, self::PRIMITIVES, true)) {
            return new PrimitiveNode($type);
        }
        if ($type === 'array' || $type === 'map') {
            $nested = 'items';
            if ($type === 'map') {
                $nested = 'values';
            }

            return [new Diagnostic(
                sprintf('"%s" cannot be \'%s\' alone: write [\'type\' => \'%s\', \'%s\' => …]', $what, $type, $type, $nested),
                $value->location,
            )];
        }
        if (str_ends_with($type, '[]')) {
            return [new Diagnostic(
                sprintf('cannot read type "%s": write type: \'array\', items: \'%s\'', $type, substr($type, 0, -2)),
                $value->location,
            )];
        }
        if (preg_match('/^map<(?:.*,)?\s*(.*)>$/', $type, $match) === 1) {
            return [new Diagnostic(
                sprintf('cannot read type "%s": write type: \'map\', values: \'%s\'', $type, $match[1]),
                $value->location,
            )];
        }

        return new RefNode($type);
    }

    /**
     * @return UnionNode|list<Diagnostic>
     */
    private function unionToNode(ConstantValue $union): UnionNode|array
    {
        if (!$union->value instanceof ConstantArray || $union->value->items === []) {
            return [new Diagnostic(
                '"union" must list its members by key: write [\'card\' => CardPayment::class, \'pix\' => \'Pix\']',
                $union->location,
            )];
        }
        $members = [];
        $keys = [];
        foreach ($union->value->items as $item) {
            $key = $item->key;
            if ($key === null || !is_string($key->value)) {
                return [new Diagnostic(
                    sprintf('a member of "union" has no key: write it as \'<key>\' => %s', $item->value->valueToText()),
                    $key->location ?? $item->value->location,
                )];
            }
            if (isset($keys[$key->value])) {
                return [new Diagnostic(sprintf('union key "%s" is written more than once: keep one', $key->value), $key->location)];
            }
            $keys[$key->value] = true;
            $node = $this->unionMemberToNode($item->value);
            if (is_array($node)) {
                return $node;
            }
            $members[] = new UnionMember($key->value, $node);
        }

        return new UnionNode($members);
    }

    /**
     * @return RefNode|list<Diagnostic>
     */
    private function unionMemberToNode(ConstantValue $member): RefNode|array
    {
        if (is_string($member->value) && $member->value !== '') {
            return new RefNode($member->value);
        }
        if ($member->value instanceof ClassReference) {
            $node = $this->types->classToSchemaNode($member->value->class, SchemaNaming::classToShortName($member->value->class), $member->location, AnnotationForm::attribute());
            if ($node instanceof RefNode || is_array($node)) {
                return $node;
            }
        }

        return [new Diagnostic(
            sprintf('cannot read %s as a member of "union": write a class with #[BidirektSchema], such as Pet::class, or a schema name', $member->valueToText()),
            $member->location,
        )];
    }
}
