<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

final class PetsController
{
    /**
     * @bidirekt provides GET /pets
     * @bidirekt response 200 Pet[]
     */
    public function list(): array
    {
        return [];
    }

    /**
     * @bidirekt provides GET /pets/featured
     * @bidirekt response 200 Pet[] as PetCollection
     */
    public function featured(): array
    {
        return [];
    }

    /**
     * @bidirekt provides GET /pets/sold
     * @bidirekt response 200 Pet[]
     */
    public function sold(): array
    {
        return [];
    }
}
