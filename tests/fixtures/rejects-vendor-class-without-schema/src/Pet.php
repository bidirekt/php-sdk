<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Carbon\Carbon;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Carbon $bornAt;

    /** @bidirekt field */
    public Carbon $adoptedAt;
}
