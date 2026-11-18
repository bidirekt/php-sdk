<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

use Acme\Http\FieldNames;
use Acme\Http\Status;

#[BidirektSchema(name: self::SCHEMA)]
final class Pet
{
    public const SCHEMA = 'PetCard';
    private const OPTIONAL = true;

    #[BidirektField(name: Labels::NICKNAME, description: Labels::CARD)]
    public string $nickname;

    #[BidirektField(FieldNames::OWNER, description: Labels::NOTE)]
    public string $owner;

    #[BidirektField(name: PetKind::Dog->value, optional: self::OPTIONAL)]
    public string $kind;

    #[BidirektField(description: Status::Sold->value)]
    public bool $sold;
}
