<?php

namespace App;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[\Bidirekt\PhpSdk\BidirektField]
    public string $name;

    #[BidirektField]
    public string $nickname;
}
