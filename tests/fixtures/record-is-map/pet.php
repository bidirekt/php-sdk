<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var array<string, float> */
    #[BidirektField]
    public array $scores;
}
