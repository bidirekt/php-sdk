<?php

namespace App\Owners;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Address
{
    #[BidirektField]
    public string $street;
}
