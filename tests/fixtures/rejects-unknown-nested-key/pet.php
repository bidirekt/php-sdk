<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'array', items: ['type' => 'array', 'item' => 'integer'])]
    public array $grid;

    #[BidirektField(type: 'map', values: ['string'])]
    public array $notes;

    #[BidirektField(type: 'map', values: ['type' => 'boolean', 'nullable' => 'yes'])]
    public array $flags;
}
