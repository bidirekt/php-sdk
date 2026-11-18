<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(nullable: false)]
    public string|null $nickname;

    #[BidirektField(optional: false)]
    public string $name;
}
