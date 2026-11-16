<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetCard', fields: [
    new BidirektField('name', type: 'string', description: PHP_EOL),
])]
final class PetResource {}

#[BidirektSchema(fields: [
    new BidirektField('name', type: 'string', description: 'A ' . 'b'),
])]
final class Owner {}

#[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => 'PetCard'])]
function pets(): array { return []; }

#[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => PetResource::class])]
function pet(): array { return []; }

#[BidirektProvider(method: 'GET', path: '/owners', responses: [200 => Owner::class])]
function owners(): array { return []; }

/**
 * @bidirekt provides GET /cards
 * @bidirekt response 200 PetCard
 */
function cards(): array { return []; }
