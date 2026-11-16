<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(items: 'string')]
    public array $tags;

    #[BidirektField(type: 'array', values: 'integer')]
    public array $scores;

    #[BidirektField(type: 'array')]
    public array $names;

    #[BidirektField(type: 'map')]
    public array $weights;

    #[BidirektField(type: 'array', items: 'array')]
    public array $grid;
}
