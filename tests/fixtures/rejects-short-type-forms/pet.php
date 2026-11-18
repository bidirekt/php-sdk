<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'Owner[]')]
    public array $owners;

    #[BidirektField(type: 'map<Owner>')]
    public array $byName;

    #[BidirektField(type: 'array', items: 'integer[]')]
    public array $grid;
}
