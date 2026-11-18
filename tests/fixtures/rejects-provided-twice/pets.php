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

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class])]
    public function find(): void {}
}

final class LegacyPetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    public function find(): void {}
}
