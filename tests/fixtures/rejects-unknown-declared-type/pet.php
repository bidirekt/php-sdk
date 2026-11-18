<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'date')]
    public DateTimeInterface $birthDate;

    #[BidirektField(type: 'map<string, integer>')]
    public array $scores;

    #[BidirektField(type: 42)]
    public mixed $owner;
}
