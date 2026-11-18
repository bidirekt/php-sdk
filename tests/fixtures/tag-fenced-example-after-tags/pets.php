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
 * @bidirekt provides POST /pets
 * @bidirekt request Pet
 * @bidirekt response 201
 *
 * ```php
 * $client->post('/pets', ['json' => $pet]);
 * ```
 */
function createPet(): void {}
