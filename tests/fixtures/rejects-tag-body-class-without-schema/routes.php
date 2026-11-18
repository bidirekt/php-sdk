<?php

use App\Http\Problem;
use App\Http\ValidationError;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class NewPet
{
    #[BidirektField]
    public string $name;
}

/**
 * @bidirekt provides POST /pets
 * @bidirekt request NewPet
 * @bidirekt response 422 ValidationError
 * @bidirekt response 400 Problem[]
 * @bidirekt response 500 ServerError
 */
function createPet(): void
{
}

/**
 * @bidirekt provides PUT /pets/*
 * @bidirekt request NewPet
 * @bidirekt response 422 ValidationError
 */
function updatePet(): void
{
}
