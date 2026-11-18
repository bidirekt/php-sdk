<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public int $id;

    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class NewPet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class NewCharge
{
    #[BidirektField]
    public int $amount;
}

#[BidirektSchema]
final class Charge
{
    #[BidirektField]
    public string $id;
}
