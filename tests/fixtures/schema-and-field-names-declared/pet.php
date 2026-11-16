<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetResponse')]
final class Pet
{
    #[BidirektField(name: 'birth_date')]
    public string $birthDate;
}
