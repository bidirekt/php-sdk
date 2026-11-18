<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetResponse')]
final class Pet
{
    #[BidirektField]
    public string $name;
}

/**
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200 PetResponse
 * @bidirekt response 404
 */
function findPet(): void
{
}

/**
 * @bidirekt provides GET /pets
 * @bidirekt response 200 PetResponse[]
 */
function listPets(): void
{
}
