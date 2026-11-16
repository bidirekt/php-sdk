<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
final class BidirektSchema
{
    /**
     * @param list<BidirektField> $fields
     */
    public function __construct(
        public ?string $name = null,
        public array $fields = [],
    ) {}
}
