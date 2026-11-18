<?php

namespace App\Marketplace\vendor;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Seller
{
    #[BidirektField]
    public string $name;
}
