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
    #[BidirektProvider(path: '/pets/*', responses: [200 => Pet::class])]
    public function find(): void {}

    #[BidirektProvider(method: 'GET', responses: [200 => [Pet::class]])]
    public function list(): void {}

    #[BidirektProvider(method: 'DELETE', path: '/pets/*')]
    public function remove(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer(method: 'GET', path: '/residents', responses: [200 => [Pet::class]])]
    public function residents(): void {}
}
