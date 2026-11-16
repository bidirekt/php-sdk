<?php

namespace App;

use Acme\Shared\Entity;
use Acme\Shared\Size;
use Acme\Shared\Status;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet extends Entity
{
    #[BidirektField]
    public Status $status;

    #[BidirektField]
    public Size $size;
}
