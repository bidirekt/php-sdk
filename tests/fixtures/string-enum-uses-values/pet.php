<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum PetStatus: string
{
    case Available = 'available';
    case OnHold = 'on_hold';
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetStatus $status;

    #[BidirektField]
    public ?PetStatus $previousStatus;
}
