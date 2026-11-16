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

const LIST_METHOD = 'GET';

final class PetsController
{
    #[BidirektProvider(method: LIST_METHOD, path: '/pets', responses: [200 => Pet::class])]
    public function list(): void {}

    #[BidirektProvider(method: 'GET', path: 7, responses: [200 => Pet::class])]
    public function find(): void {}

    #[BidirektProvider(method: 'POST', path: '/pets', responses: Pet::class)]
    public function create(): void {}

    #[BidirektProvider(method: 'DELETE', path: '/pets/*', responses: ['gone' => null])]
    public function remove(): void {}
}

final class ShelterClient
{
    #[BidirektConsumer(method: 'GET', path: '/residents', responses: [200 => Pet::class])]
    public function residents(): void {}
}
