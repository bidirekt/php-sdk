<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [
    new BidirektField('name', type: 'string'),
    new BidirektField('birth_date', type: 'string'),
])]
final class Pet
{
    #[BidirektField]
    public string $name;

    #[BidirektField(name: 'birth_date')]
    public string $birthDate;
}
