<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var array<int, string> */
    #[BidirektField]
    public array $byId;

    /** @var array<string> */
    #[BidirektField]
    public array $names;
}
