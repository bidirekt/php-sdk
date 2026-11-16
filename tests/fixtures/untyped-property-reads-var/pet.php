<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var string */
    #[BidirektField]
    public $name;

    /** @var ?string */
    #[BidirektField]
    public $nickname;

    /** @var list<string> */
    #[BidirektField]
    public $tags;
}
