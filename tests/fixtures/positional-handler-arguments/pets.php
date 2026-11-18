<?php

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

final class PetsController
{
    #[BidirektProvider('GET', '/pets/*', [200 => Pet::class, 404 => null])]
    public function find(): void {}

    #[BidirektProvider('PUT', '/pets/*', [204 => null], Pet::class)]
    public function replace(): void {}

    #[BidirektProvider('POST', '/pets', request: Pet::class, responses: [201 => Pet::class])]
    public function create(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer('shelter_api', 'GET', '/residents', [200 => [Pet::class]])]
    public function residents(): void {}

    #[BidirektConsumer('shelter_api', 'POST', '/residents', [201 => null], null)]
    public function admit(): void {}
}
