<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

trait Identified
{
    #[BidirektField]
    public int $id;
}

trait Timestamped
{
    use Identified;

    #[BidirektField]
    public string $createdAt;
}

#[BidirektSchema]
final class Pet
{
    use Timestamped;

    #[BidirektField]
    public string $name;
}
