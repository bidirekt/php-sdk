<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    public function __construct(
        #[BidirektField] public PetStatus $status,
    ) {}
}
