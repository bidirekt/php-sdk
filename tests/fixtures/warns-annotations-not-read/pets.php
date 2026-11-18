<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;

    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => Pet::class])]
    public string $owner;
}

final class PetsController
{
    #[BidirektField]
    public function find(): void {}

    #[BidirektSchema]
    public const LIST = 'list';
}
