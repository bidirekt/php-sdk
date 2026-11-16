<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * The pet the API returns, checked by @bidirekt tooling.
 * @internal Kept in sync by @bidirekt tooling.
 */
#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

/** Lists the pets. @bidirekt provides GET /pets */
function listPets(): void {}

/**
 * Finds a pet. @bidirekt provides GET /pets/*
 * It answers 200. @bidirekt response 200
 */
function findPet(): void {}
