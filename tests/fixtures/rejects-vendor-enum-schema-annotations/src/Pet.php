<?php

namespace App;

use Acme\Shared\Color;
use Acme\Shared\Flag;
use Acme\Shared\Level;
use Acme\Shared\Size;
use Acme\Shared\Status;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Status $status;

    #[BidirektField]
    public Size $size;

    #[BidirektField]
    public Color $color;

    #[BidirektField]
    public Level $level;

    #[BidirektField]
    public Flag $flag;
}
