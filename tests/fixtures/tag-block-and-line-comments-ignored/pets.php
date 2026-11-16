<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

/*
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200 Pet
 */
function findPet(): void
{
}

// @bidirekt provides GET /pets
// @bidirekt response 200 Pet
function listPets(): void
{
}

# @bidirekt provides DELETE /pets/*
# @bidirekt response 204
function deletePet(): void
{
}

/**
 * @bidirekt provides POST /pets
 * @bidirekt response 201 Pet
 */
function createPet(): void
{
}
