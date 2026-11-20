<?php

declare(strict_types=1);

namespace App\Domain;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class StatusChange
{
    public function __construct(
        #[BidirektField(description: 'available pending or sold')] public string $status,
    ) {
    }
}
