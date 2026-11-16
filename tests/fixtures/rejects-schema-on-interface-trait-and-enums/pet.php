<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
interface Named
{
}

#[BidirektSchema]
trait Timestamped
{
    #[BidirektField]
    public string $createdAt;
}

#[BidirektSchema]
enum Level: int
{
    case Low = 1;
}

#[BidirektSchema]
enum Flag
{
    case On;
}
