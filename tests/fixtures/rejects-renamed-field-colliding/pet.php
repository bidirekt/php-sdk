<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(name: 'birth_date')]
    public string $birthDate;

    #[BidirektField]
    public string $birth_date;
}
