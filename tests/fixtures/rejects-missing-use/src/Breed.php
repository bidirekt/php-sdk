<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;

#[BidirektSchema]
final class Breed
{
    #[BidirektField]
    public string $name;

    #[BidirektField]
    public ?Breed $parent;
}
