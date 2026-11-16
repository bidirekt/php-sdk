<?php

use Bidirekt\PhpSdk\BidirektField as Field;
use Bidirekt\PhpSdk\BidirektSchema as Schema;

#[Schema]
final class Pet
{
    #[Field]
    public string $name;
}
