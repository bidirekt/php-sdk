<?php

namespace App;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    public function __construct(
        #[BidirektField] public int $ownerId,
        #[BidirektField] public string $name,
    ) {}
}
