<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum Priority: int
{
    case Low = 0;
    case High = 10;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Priority $priority;
}
