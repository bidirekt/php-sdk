<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

abstract class Animal
{
    #[BidirektField(type: 'Ownr')]
    public $owner;

    #[BidirektField(name: 'kind')]
    public string $name;

    #[BidirektField]
    public string $kind;
}

#[BidirektSchema]
final class Dog extends Animal {}

#[BidirektSchema]
final class Cat extends Animal {}
