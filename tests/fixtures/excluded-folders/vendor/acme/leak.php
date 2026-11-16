<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class VendorLeak
{
    #[BidirektField]
    public string $name;
}
