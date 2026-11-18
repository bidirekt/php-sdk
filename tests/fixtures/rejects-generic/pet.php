<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

final class Collection
{
}

#[BidirektSchema]
final class Pet
{
    /** @var Collection<int, Owner> */
    #[BidirektField]
    public $owners;
}
