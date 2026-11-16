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
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => 'Pett'])]
    public function find(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer(provider: 'shelter_api', method: 'POST', path: '/residents', request: 'NewPet', responses: [201 => ['Pets']])]
    public function admit(): void {}
}
