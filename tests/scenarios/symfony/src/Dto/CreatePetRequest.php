<?php

namespace App\Dto;

use App\Enum\PetStatus;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

#[BidirektSchema(name: 'NewPet')]
final class CreatePetRequest
{
    /**
     * @param list<string> $photoUrls
     * @param list<string> $tags
     */
    public function __construct(
        #[BidirektField]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $name,
        #[BidirektField(optional: true)]
        public PetStatus $status = PetStatus::Available,
        #[BidirektField(optional: true)]
        #[Assert\Positive]
        public ?int $categoryId = null,
        #[BidirektField(name: 'photo_urls', optional: true)]
        #[SerializedName('photo_urls')]
        #[Assert\All([new Assert\Url()])]
        public array $photoUrls = [],
        #[BidirektField(optional: true)]
        #[Assert\All([new Assert\NotBlank()])]
        public array $tags = [],
    ) {
    }
}
