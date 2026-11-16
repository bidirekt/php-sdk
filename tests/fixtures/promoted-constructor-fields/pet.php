<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    public function __construct(
        #[BidirektField] public int $petId,
        #[BidirektField(description: 'Shown on the card')] private string $name,
        #[BidirektField] protected bool $vaccinated,
        public string $internalNotes,
    ) {
    }
}
