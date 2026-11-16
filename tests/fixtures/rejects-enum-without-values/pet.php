<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum PetStatus
{
    case Available;
    case Sold;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetStatus $status;
}
