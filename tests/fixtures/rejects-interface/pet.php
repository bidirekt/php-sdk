<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

interface Address
{
    public function street(): string;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Address $address;
}
