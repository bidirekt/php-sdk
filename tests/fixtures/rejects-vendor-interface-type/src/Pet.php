<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Doctrine\Common\Collections\Collection;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Carbon $bornAt;

    #[BidirektField]
    public CarbonInterface $updatedAt;

    /** @var Collection<int, Pet> */
    #[BidirektField]
    public Collection $friends;

    #[BidirektField]
    public \DateTimeInterface $createdAt;
}
