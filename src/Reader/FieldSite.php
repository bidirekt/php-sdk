<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * A property or parameter of a file with `#[BidirektField]` or `@bidirekt field`, warned about when no
 * schema reads it.
 */
final class FieldSite
{
    /**
     * @param list<SdkAttribute> $attributes its SDK attributes, a #[BidirektField] among them unless it has a field tag
     * @param ClassSummary|null $owner the class-like it is written in
     */
    public function __construct(
        public string $key,
        public array $attributes,
        public bool $notPromotedParameter,
        public ?ClassSummary $owner,
    ) {}
}
