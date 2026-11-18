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
     * Finds a pet.
     *
     * @Route("/pets/{id}", methods={"GET"})
     * @OA\Get(path="/pets/{id}", @OA\Response(response=200, description="the pet"))
     * @bidirekt provides GET /pets/*
     * @param string $id the pet id
     * @bidirekt response 200 Pet
     * @return Pet the pet
     * @throws NotFound when there is no pet
     */
    public function find(string $id): Pet
    {
        return new Pet();
    }
}
