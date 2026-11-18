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
 * @bidirekt response 200 Pet
 */
function findPet(): void
{
}

/**
 * @bidirekt provides POST /pets
 * @bidirekt request NewPet
 * @bidirekt response 201 PetResponse
 */
function createPet(): void
{
}

/**
 * @bidirekt provides GET /pets
 * @bidirekt response 200 Puppy[]
 */
function listPets(): void
{
}

/**
 * @bidirekt provides GET /owners
 * @bidirekt response 200 \
 */
function listOwners(): void
{
}

/**
 * @bidirekt provides POST /owners
 * @bidirekt request namespace\
 */
function createOwner(): void
{
}
