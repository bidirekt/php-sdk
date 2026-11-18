<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    /** @var list<string> */
    public array $tags;

    public function __construct(
        #[BidirektField]
        /** @var list<string> */
        public array $nicknames,
    ) {}
}
