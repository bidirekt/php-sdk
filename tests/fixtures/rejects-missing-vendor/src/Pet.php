<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

use Acme\Model\Entity;

#[BidirektSchema]
final class Pet extends Entity
{
    #[BidirektField]
    public string $name;
}
