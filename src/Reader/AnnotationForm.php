<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * How messages tell the fix in the form the element is annotated with: an attribute or a docblock tag.
 */
final class AnnotationForm
{
    /**
     * @param string $typeAndUnion the declaration takes `type` or `union`, `%s` being where they are written
     * @param string $nestedWithoutKind `items` or `values` without its `type`: `%1$s` the type (array, map),
     *                                  `%2$s` the key (items, values), `%3$s` where they are written
     * @param string $kindWithoutNested a `type` without its `items` or `values`, with the arguments of `$nestedWithoutKind`
     * @param string $shortForm how to write a short form like `Pet[]`: `%1$s` the type, `%2$s` its key, `%3$s` the key's type
     */
    private function __construct(
        public string $schemaAnnotation,
        public string $declareSchemaName,
        public string $fieldAnnotation,
        public string $declareType,
        public string $declareArray,
        public string $declareUnion,
        public string $placeUnion,
        public string $addSchema,
        public string $typeAndUnion,
        public string $nestedWithoutKind,
        public string $kindWithoutNested,
        public string $shortForm,
    ) {}

    public static function attribute(): self
    {
        return new self(
            '#[BidirektSchema]',
            '#[BidirektSchema(name: …)]',
            '#[BidirektField]',
            '#[BidirektField(type: …)]',
            '#[BidirektField(type: \'array\', items: …)]',
            '#[BidirektField(union: …)]',
            'the "union" of #[BidirektField]',
            'add #[BidirektSchema] to it',
            'declare either "type" or "union" in %s, not both',
            '"%2$s" is declared without type: \'%1$s\': write type: \'%1$s\' or remove "%2$s"',
            'type: \'%1$s\' needs "%2$s": write the type of its %2$s, as in %2$s: \'Pet\'',
            'type: \'%1$s\', %2$s: \'%3$s\'',
        );
    }

    public static function tag(): self
    {
        return new self(
            '"@bidirekt schema"',
            '"@bidirekt schema name=<name>"',
            '"@bidirekt field"',
            '"@bidirekt field type=<type>"',
            '"@bidirekt field type=array items=<type>"',
            '"@bidirekt field union={<key>: <schema>, ...}"',
            'the "union=" of "@bidirekt field"',
            'add "@bidirekt schema" above it',
            'declare either "type=" or "union=" in %s, not both',
            '"%2$s=" is declared without type=%1$s in %3$s: write type=%1$s or remove "%2$s="',
            'type=%1$s needs "%2$s=" in %3$s: write the type of its %2$s, as in %2$s=Pet',
            'type=%1$s %2$s=%3$s',
        );
    }
}
