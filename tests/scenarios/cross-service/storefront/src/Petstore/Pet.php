<?php

declare(strict_types=1);

namespace Storefront\Petstore;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * A pet as the storefront reads it from the pet store API: only the fields the shop window shows.
 */
#[BidirektSchema]
final class Pet
{
    public function __construct(
        #[BidirektField] public int $id,
        #[BidirektField] public string $name,
        #[BidirektField] public string $status,
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function fromBody(array $body): self
    {
        return new self((int) $body['id'], (string) $body['name'], (string) $body['status']);
    }
}
