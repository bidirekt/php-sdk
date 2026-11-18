<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(null)]
final class Pet
{
    #[BidirektField(null, 'integer')]
    public float $age;

    #[BidirektField(description: null, optional: null, nullable: null, union: null, items: null, values: null)]
    public string $name;
}

#[BidirektSchema(name: null, fields: null)]
final class Owner
{
    #[BidirektField(name: null, type: null)]
    public string $name;
}
