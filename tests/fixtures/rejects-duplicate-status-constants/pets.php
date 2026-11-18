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

final class Status
{
    public const OK = 200;
    public const SUCCESS = 200;
}

final class PetsController
{
    #[BidirektProvider(
        method: 'GET',
        path: '/pets/*',
        responses: [
            Status::OK => Pet::class,
            Status::SUCCESS => null,
        ],
    )]
    public function find(): void {}

    #[BidirektProvider(
        method: 'GET',
        path: '/pets',
        responses: [
            200 => [Pet::class],
            Status::OK => null,
        ],
    )]
    public function list(): void {}
}
