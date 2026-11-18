<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'string')]
    public ?DateTimeImmutable $birthDate;

    #[BidirektField(type: 'string', optional: true, description: 'ISO 8601')]
    public DateTimeImmutable $adoptedAt;

    /** @var list<float> */
    #[BidirektField(type: 'array', items: 'integer')]
    public array $scores;

    #[BidirektField(type: 'array', items: ['type' => 'array', 'items' => 'integer'])]
    public array $grid;

    #[BidirektField(type: 'map', values: 'float')]
    public array $weights;

    #[BidirektField(type: 'map', values: ['type' => 'array', 'items' => 'float'])]
    public array $readings;

    #[BidirektField(type: 'array', items: ['type' => 'map', 'values' => 'boolean'], optional: true)]
    public mixed $checks;
}
