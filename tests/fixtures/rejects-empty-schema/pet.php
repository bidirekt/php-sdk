<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Blank
{
}

#[BidirektSchema]
final class Pet
{
    public string $name;
}

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Shelter
{
    #[BidirektField]
    public Blank $blank;

    #[BidirektField]
    public Pet $pet;
}
