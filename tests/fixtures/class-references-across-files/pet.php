<?php

namespace App\Pets;

use App\Owners;
use App\Owners\Address;
use App\Owners\Owner;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class PetCard
{
    #[BidirektField]
    public Owner $owner;

    /** @var list<Owners\Owner> */
    #[BidirektField]
    public array $formerOwners;

    #[BidirektField]
    public Address $address;
}
