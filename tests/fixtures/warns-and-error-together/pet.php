<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public mixed $name;

    #[BidirektField]
    public static string $kind;

    #[BidirektField]
    public object $owner;
}
