<?php

declare(strict_types=1);

namespace App\Domain;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use JsonSerializable;

#[BidirektSchema]
final class Pet implements JsonSerializable
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        #[BidirektField] public int $petId,
        #[BidirektField] public string $name,
        #[BidirektField(description: 'available, pending or sold')] public string $status,
        #[BidirektField(type: 'float')] public float $weight,
        #[BidirektField] public int $ownerId,
        #[BidirektField] public string $photoUrl,
        #[BidirektField] public ?Category $category,
        #[BidirektField] public array $tags,
        public string $internalNotes = '',
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'petId' => $this->petId,
            'name' => $this->name,
            'status' => $this->status,
            'weight' => $this->weight,
            'ownerId' => $this->ownerId,
            'category' => $this->category,
            'tags' => $this->tags,
        ];
    }
}
