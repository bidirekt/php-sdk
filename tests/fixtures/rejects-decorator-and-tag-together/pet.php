<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/** @bidirekt schema */
#[BidirektSchema]
final class Pet
{
    /** @bidirekt field */
    #[BidirektField]
    public string $name;

    public function __construct(
        /** @bidirekt field */
        #[BidirektField]
        public string $breed,
    ) {}
}

/** @bidirekt schema */
#[BidirektSchema]
enum Status: string
{
    case Available = 'available';
}
