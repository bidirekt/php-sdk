<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

/** @bidirekt schema name=PetCard */
$petCard = ['name' => 'Rex'];

/** @bidirekt field name=pet_name */
function petName(): string
{
    return 'Rex';
}

final class Owner
{
    /** @bidirekt schema */
    public string $name;

    /** @bidirekt field */
    public function rename(): void
    {
    }

    /** @bidirekt field */
    public const KIND = 'owner';
}
