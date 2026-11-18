<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Resource
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Integer
{
    #[BidirektField]
    public int $value;
}

#[BidirektSchema]
final class Booking
{
    #[BidirektField]
    public Resource $resource;

    #[BidirektField]
    public ?Integer $count;

    /** @var list<\App\Resource> */
    #[BidirektField]
    public array $alternatives;
}
