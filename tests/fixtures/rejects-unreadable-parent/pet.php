<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
class Dog extends Timestamped
{
    #[BidirektField]
    public string $breed;
}

#[BidirektSchema]
final class Puppy extends Dog
{
}

#[BidirektSchema]
final class Cat
{
    use Named;
}
