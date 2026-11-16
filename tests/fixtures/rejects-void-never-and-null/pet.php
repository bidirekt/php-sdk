<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var void */
    #[BidirektField]
    public $a;

    /** @var never */
    #[BidirektField]
    public $b;

    /** @var null */
    #[BidirektField]
    public $c;
}
