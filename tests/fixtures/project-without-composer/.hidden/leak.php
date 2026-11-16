<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class HiddenLeak
{
    #[BidirektField]
    public string $name;
}
