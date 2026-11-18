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
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: ['2xx' => Pet::class])]
    public function find(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets', responses: [-200 => null, true => null])]
    public function list(): void {}
}
