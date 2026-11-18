<?php

namespace App;

#[Bidirekt\PhpSdk\BidirektSchema]
final class Pet
{
    #[PhpSdk\BidirektField]
    public string $name;
}

final class PetController
{
    #[Bidirekt\PhpSdk\BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class])]
    public function show(): void {}

    #[\Bidirekt\PhpSdk\BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])]
    #[\Bidirekt\PhpSdk\BidirektSchema(name: 'Health', fields: [
        new Bidirekt\PhpSdk\BidirektField('status', type: 'string'),
    ])]
    public function health(): void {}
}
