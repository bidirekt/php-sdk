<?php

namespace App\Dto;

use App\Enum\PetStatus;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[BidirektSchema(name: 'Pet')]
final class PetResponse extends Resource
{
    /**
     * @param list<string> $photoUrls
     * @param list<string> $tags
     */
    public function __construct(
        int $id,
        \DateTimeImmutable $createdAt,
        #[BidirektField]
        public readonly string $name,
        #[BidirektField]
        public readonly PetStatus $status,
        #[BidirektField]
        public readonly ?CategoryResponse $category,
        #[BidirektField(name: 'photo_urls')]
        #[SerializedName('photo_urls')]
        public readonly array $photoUrls,
        #[BidirektField]
        public readonly array $tags,
    ) {
        parent::__construct($id, $createdAt);
    }
}
