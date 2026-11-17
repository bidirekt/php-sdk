<?php

namespace App\Dto;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * What #[MapRequestPayload] answers when the payload fails validation; the framework writes it, never the app.
 */
#[BidirektSchema]
final class ValidationProblem extends Problem
{
    /**
     * @var list<Violation>
     */
    #[BidirektField]
    public array $violations = [];
}
