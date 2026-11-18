<?php

namespace App\Dto;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Violation
{
    #[BidirektField]
    public string $propertyPath;

    #[BidirektField]
    public string $title;

    #[BidirektField]
    public string $template;

    /**
     * @var array<string, string>
     */
    #[BidirektField]
    public array $parameters;

    #[BidirektField(optional: true, description: 'urn:uuid: of the failed constraint')]
    public string $type;
}
