<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class TestsLeak
{
    #[BidirektField]
    public string $name;
}
