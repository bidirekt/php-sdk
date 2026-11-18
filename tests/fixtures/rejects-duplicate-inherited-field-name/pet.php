<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

class Entity
{
    #[BidirektField(name: 'id')]
    public string $identifier;
}

#[BidirektSchema]
final class Pet extends Entity
{
    #[BidirektField]
    public string $id;
}
