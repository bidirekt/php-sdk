<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var list<string> */
    #[BidirektField]
    public array $tags;

    /** @var list<float> */
    #[BidirektField]
    public array $scores;

    /** @var string[] */
    #[BidirektField]
    public array $aliases;
}
