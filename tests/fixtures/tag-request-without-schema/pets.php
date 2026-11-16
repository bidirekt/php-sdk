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
 * @bidirekt provides PUT /pets/*
 * @bidirekt request
 * @bidirekt response 200 Pet
 * @bidirekt response 404
 */
function replacePet(): void
{
}
