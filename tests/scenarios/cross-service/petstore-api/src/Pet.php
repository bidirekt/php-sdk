<?php

declare(strict_types=1);

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    public function __construct(
        #[BidirektField] public int $id,
        #[BidirektField] public string $name,
        #[BidirektField] public PetStatus $status,
        #[BidirektField(description: 'ISO 8601 date')] public ?string $birthDate,
    ) {
    }
}
