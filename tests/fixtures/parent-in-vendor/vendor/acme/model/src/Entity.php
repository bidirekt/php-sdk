<?php

namespace Acme\Model;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

abstract class Entity
{
    use Timestamps;

    #[BidirektField]
    public int $id;

    #[BidirektField]
    public Visibility $visibility;
}
