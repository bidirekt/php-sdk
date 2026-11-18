<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;

    #[BidirektField]
    public string $broken =;

    #[BidirektField]
    public int $age;
}
