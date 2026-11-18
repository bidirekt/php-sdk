<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class PetList
{
    #[BidirektField]
    public int $total;
}

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => [Pet::class]])]
    public function list(): void {}
}
