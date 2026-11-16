<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(nmae: 'Keeper')]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema(name: '')]
final class Vet
{
    #[BidirektField]
    public string $name;
}

/**
 * @bidirekt schema nmae=Breeder
 */
final class Breeder
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Owner $owner;

    #[BidirektField]
    public Vet $vet;

    #[BidirektField]
    public Breeder $breeder;
}

#[BidirektProvider(method: 'GET', path: '/owners', responses: [200 => Owner::class])]
function owners() {}
