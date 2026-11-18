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
    #[BidirektField]
    public Owner $owner;

    /** @var list<Owner> */
    #[BidirektField]
    public array $previousOwners;
}
