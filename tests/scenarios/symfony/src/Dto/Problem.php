<?php

namespace App\Dto;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * An error as Symfony's ProblemNormalizer writes it (RFC 7807).
 */
#[BidirektSchema]
class Problem
{
    public function __construct(
        #[BidirektField] public string $type,
        #[BidirektField] public string $title,
        #[BidirektField] public int $status,
        #[BidirektField] public string $detail,
    ) {
    }
}
