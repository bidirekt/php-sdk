<?php

namespace Acme\Model;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
class Entity
{
    #[BidirektField]
    public int $id;
}
