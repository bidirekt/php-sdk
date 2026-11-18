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
 * @bidirekt provides GET
 * @bidirekt response 200 Pet
 */
function missingPath(): void
{
}

/**
 * @bidirekt consumes POST /charges
 * @bidirekt response 201
 */
function missingProvider(): void
{
}

/**
 * @bidirekt provides GET /pets/*
 * @bidirekt response
 * @bidirekt response ok Pet
 * @bidirekt response 200 Pet as PetCard
 * @bidirekt response 404
 * @bidirekt response 404
 */
function badResponses(): void
{
}

/**
 * @bidirekt provides PUT /pets/*
 * @bidirekt request Pet
 * @bidirekt request
 * @bidirekt response 204
 */
function twoRequests(): void
{
}

/**
 * @bidirekt provides GET /pets
 * @bidirekt provides GET /pets/sold
 * @bidirekt response 200 Pet[] as
 */
function twoHandlers(): void
{
}

/**
 * @bidirekt response 200 Pet
 */
function responseWithoutHandler(): void
{
}

/**
 * @bidirekt
 * @bidirekt handles GET /pets
 */
function unknownKeyword(): void
{
}

/**
 * @bidirekt schema Status
 */
enum Status: string
{
    case Available = 'available';
}

/**
 * @bidirekt provides GET /pets/legacy
 * @bidirekt response 0200 Pet
 */
function leadingZeroStatus(): void
{
}
