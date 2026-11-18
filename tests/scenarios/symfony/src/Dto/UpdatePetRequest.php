<?php

namespace App\Dto;

use App\Enum\PetStatus;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Symfony\Component\Validator\Constraints as Assert;

#[BidirektSchema(name: 'PetChange')]
final class UpdatePetRequest
{
    public function __construct(
        #[BidirektField(optional: true)]
        #[Assert\Length(min: 1, max: 100)]
        public ?string $name = null,
        #[BidirektField(optional: true)]
        public ?PetStatus $status = null,
    ) {
    }
}
