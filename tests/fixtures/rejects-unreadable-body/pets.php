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

final class Owner
{
    public string $name;
}

enum Rank: int
{
    case First = 1;
}

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => true])]
    public function find(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => [Pet::class, Pet::class]])]
    public function list(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/featured', responses: [200 => ['items' => Pet::class, 'name' => 'Featured']])]
    public function featured(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/*/owner', responses: [200 => Owner::class])]
    public function owner(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/*/owners', responses: [200 => [Owner::class]])]
    public function owners(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/*/rank', responses: [200 => Rank::class])]
    public function rank(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/*/tags', responses: [200 => [null]])]
    public function tags(): void {}

    #[BidirektProvider(method: 'POST', path: '/pets', request: '', responses: [201 => null])]
    public function create(): void {}
}
