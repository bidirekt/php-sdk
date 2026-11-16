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

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

final class ShelterController
{
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => ['list' => Pet::class, 'name' => 'Residents']])]
    public function pets(): void {}

    #[BidirektProvider(method: 'GET', path: '/owners', responses: [200 => ['list' => Owner::class, 'name' => 'Residents']])]
    public function owners(): void {}
}
