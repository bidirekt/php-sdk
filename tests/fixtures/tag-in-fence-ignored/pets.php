<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * The pet the API returns. Declare a field like this:
 * ```php
 * @bidirekt field type=integer
 * public $id;
 * ```
 */
#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

/**
 * Function handlers declare their contract in a docblock:
 * ```php
 * @bidirekt provides GET /health
 * @bidirekt response 200
 * @Bidirekt provides GET /typo
 * ```
 */
function documented(): void {}
