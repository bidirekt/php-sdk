<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet extends \ArrayObject
{
    #[BidirektField]
    public string $name;

    #[BidirektField]
    public \DateTimeImmutable $birthDate;
}
