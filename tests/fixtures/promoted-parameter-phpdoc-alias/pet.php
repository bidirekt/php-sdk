<?php

namespace App\Http;

use App\Domain\Pet as P;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Litter
{
    /**
     * @param list<\App\Domain\Pet> $pets
     */
    public function __construct(
        /** @var list<P> */
        #[BidirektField] public array $pets,
    ) {}
}
