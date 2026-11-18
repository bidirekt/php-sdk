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
    #[BidirektProvider(
        method: 'GET',
        path: '/pets/*',
        responses: [
            200 => Pet::class,
            '0200' => null,
        ],
    )]
    public function find(): void {}

    #[BidirektProvider(
        method: 'GET',
        path: '/pets',
        responses: [
            0200 => Pet::class,
        ],
    )]
    public function list(): void {}
}
