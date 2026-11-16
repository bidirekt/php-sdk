<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

final class Address
{
    public string $street;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Address $address;
}
