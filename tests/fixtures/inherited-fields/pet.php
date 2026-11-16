<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

class Entity
{
    #[BidirektField]
    public float $id;
}

#[BidirektSchema]
class Pet extends Entity
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Dog extends Pet
{
    #[BidirektField]
    public string $breed;
}
