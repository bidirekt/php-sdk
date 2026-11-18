<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}
