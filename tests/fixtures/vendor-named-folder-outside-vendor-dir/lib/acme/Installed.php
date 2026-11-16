<?php

namespace Acme;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Installed
{
    #[BidirektField]
    public string $name;
}
