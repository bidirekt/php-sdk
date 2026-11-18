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
     * @param bool $readable false when one of its arguments cannot be read, so it declares its name alone
     */
    public function __construct(
        public ?string $declaredName,
        public AnnotationForm $form,
        public ?ConstantValue $fields,
        public bool $readable = true,
    ) {}
}
