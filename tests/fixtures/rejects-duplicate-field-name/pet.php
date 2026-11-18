<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(name: 'created_at')]
    public string $createdAt;

    #[BidirektField(name: 'created_at')]
    public string $updatedAt;
}
