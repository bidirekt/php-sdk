<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * What the `#[BidirektSchema]` or `@bidirekt schema` of a class-like declares.
 */
final class SchemaAnnotation
{
    /**
     * @param ConstantValue|null $fields the `fields:` of its #[BidirektSchema]
     */
    public function __construct(
        public ?string $declaredName,
        public AnnotationForm $form,
        public ?ConstantValue $fields,
    ) {}
}
