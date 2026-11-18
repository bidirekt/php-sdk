<?php

declare(strict_types=1);

namespace Acme\Api\Http;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Spatie\DataTransferObject\DataTransferObject;

#[BidirektSchema]
final class Problem extends DataTransferObject
{
    #[BidirektField]
    public string $title;

    #[BidirektField]
    public int $status;
}
