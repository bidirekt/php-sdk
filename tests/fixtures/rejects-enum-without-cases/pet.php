<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetStatus $status;

    #[BidirektField]
    public PetSize $size;

    #[BidirektField]
    public PetColor $color;

    #[BidirektField]
    public PetColor $otherColor;
}

#[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => Pet::class, 201 => PetColor::class])]
function listPets(): void {}
