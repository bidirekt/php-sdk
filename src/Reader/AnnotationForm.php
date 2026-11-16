<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * How messages tell the fix in the form the element is annotated with: an attribute or a docblock tag.
 */
final class AnnotationForm
{
    private function __construct(
        public string $schemaAnnotation,
        public string $declareSchemaName,
        public string $fieldAnnotation,
        public string $declareType,
        public string $declareArray,
        public string $declareUnion,
        public string $placeUnion,
        public string $addSchema,
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
        );
    }
}
