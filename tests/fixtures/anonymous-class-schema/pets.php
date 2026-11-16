<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

$pet = new #[BidirektSchema(name: 'Pet')] class {
    #[BidirektField]
    public string $name;
};

$owner = new
/** @bidirekt schema name=Owner */
class {
    /** @bidirekt field type=string */
    public $name;
};

#[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => 'Pet'])]
function listPets(): void {}

/**
 * @bidirekt provides GET /owners
 * @bidirekt response 200 Owner
 */
function listOwners(): void {}
