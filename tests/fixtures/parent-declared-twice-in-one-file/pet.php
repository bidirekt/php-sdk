<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet extends Base
{
    #[BidirektField]
    public string $name = '';
}

#[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => Pet::class])]
function listPets(): void {}
