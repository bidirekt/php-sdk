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
 * How an annotated controller declares its contract.
 * @example
 * @Route("/health")
 * public function health() {}
 * @bidirekt provides GET /health
 * @bidirekt response 200
 */
function documented(): void {}
