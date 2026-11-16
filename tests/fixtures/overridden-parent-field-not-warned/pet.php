<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

class Animal
{
    #[BidirektField]
    public string $name;

    #[BidirektField]
    public float $age;
}

#[BidirektSchema]
final class Dog extends Animal
{
    #[BidirektField(description: 'The name the owner calls the dog')]
    public string $name;
}
