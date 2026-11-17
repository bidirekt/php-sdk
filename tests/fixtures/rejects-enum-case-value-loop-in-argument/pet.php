<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum PetKind: string
{
    case Cat = self::Dog->value;
    case Dog = self::Cat->value;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(description: PetKind::Cat->value)]
    public string $kind;
}
