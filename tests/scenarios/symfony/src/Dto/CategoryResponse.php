<?php

namespace App\Dto;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'Category')]
final readonly class CategoryResponse
{
    public function __construct(
        #[BidirektField] public int $id,
        #[BidirektField] public string $name,
    ) {
    }
}
