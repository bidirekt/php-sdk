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
    #[BidirektField]
    public Owner|string $owner;
}
