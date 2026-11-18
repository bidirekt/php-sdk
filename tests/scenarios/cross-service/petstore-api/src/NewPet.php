<?php

declare(strict_types=1);

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class NewPet
{
    public function __construct(
        #[BidirektField] public string $name,
        #[BidirektField(optional: true)] public ?string $birthDate = null,
    ) {
    }

    /**
     * @param array<mixed> $body
     */
    public static function fromBody(array $body): ?self
    {
        if (!is_string($body['name'] ?? null)) {
            return null;
        }
        $birthDate = $body['birthDate'] ?? null;

        return new self($body['name'], is_string($birthDate) ? $birthDate : null);
    }
}
