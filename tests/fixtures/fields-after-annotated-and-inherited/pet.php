<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

abstract class Entity
{
    #[BidirektField]
    public int $id;
}

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema(name: 'Pet', fields: [
    new BidirektField('owner', type: Owner::class),
    new BidirektField('owner_id', type: 'integer'),
    new BidirektField('tags', type: 'map', values: ['type' => 'array', 'items' => 'string']),
])]
final class PetModel extends Entity
{
    public function __construct(
        #[BidirektField] public string $name,
        #[BidirektField(name: 'birth_date')] public ?string $birthDate,
    ) {}
}
