<?php

namespace App;

use Other\BidirektField;

#[BidirektSchema]
final class Tag
{
    #[BidirektField]
    public string $label;
}
