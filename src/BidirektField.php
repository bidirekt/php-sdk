<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class BidirektField
{
    /**
     * @param string|array<string, mixed>|null $items
     * @param string|array<string, mixed>|null $values
     * @param array<string, string>|null $union
     */
    public function __construct(
        public ?string $name = null,
        public ?string $type = null,
        public string|array|null $items = null,
        public string|array|null $values = null,
        public ?bool $optional = null,
        public ?bool $nullable = null,
        public ?string $description = null,
        public ?array $union = null,
    ) {}
}
