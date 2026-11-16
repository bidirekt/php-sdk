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
 * @bidirekt provides get /pets/*
 * @bidirekt response 200 Pet
 */
function findPet(): void
{
}

/**
 * @bidirekt consumes payments_api Post /charges
 * @bidirekt response 201
 */
function charge(): void
{
}
