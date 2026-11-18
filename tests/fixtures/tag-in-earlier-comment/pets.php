<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

/**
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200 Pet
 */
/** Finds a pet by its id. */
function findPet(string $id): string
{
    return $id;
}

/** @bidirekt schema name=PetStatus */
/** The statuses a pet goes through. */
enum Status: string
{
    case Available = 'available';
}

#[BidirektSchema]
final class Listing
{
    #[BidirektField]
    public Status $status;
}
