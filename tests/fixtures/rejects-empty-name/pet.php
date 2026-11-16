<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: '')]
final class Pet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Owner
{
    #[BidirektField(name: '')]
    public string $name;
}

final class OwnersController
{
    #[BidirektProvider(
        method: 'GET',
        path: '/owners',
        responses: [200 => ['list' => Owner::class, 'name' => '']],
    )]
    public function list(): void {}
}
