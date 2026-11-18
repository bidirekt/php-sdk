<?php

namespace App\Dto;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final readonly class PetPage
{
    /**
     * @param list<PetResponse> $items
     */
    public function __construct(
        #[BidirektField] public array $items,
        #[BidirektField] public int $page,
        #[BidirektField(description: 'Number of pets across all pages')] public int $total,
    ) {
    }
}
