<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var list<self> */
    #[BidirektField]
    public array $children;

    /** @var static|null */
    #[BidirektField]
    public $mother;
}
