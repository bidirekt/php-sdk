<?php

namespace App\Shelters;

use App\Pet as P;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Shelter
{
    /** @var list<P> */
    #[BidirektField]
    public array $residents;

    /** @var array<string, \App\Pet> */
    #[BidirektField]
    public array $byName;
}
