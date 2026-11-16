<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetOwner')]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: Owner::class)]
    public array $owner;

    #[BidirektField(type: 'array', items: Owner::class)]
    public array $owners;

    #[BidirektField(type: 'PetOwner')]
    public ?array $keeper;
}
