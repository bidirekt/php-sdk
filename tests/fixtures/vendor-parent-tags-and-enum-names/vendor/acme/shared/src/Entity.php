<?php

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektField;

abstract class Entity
{
    /** @bidirekt field type=integer */
    public $id;

    #[BidirektField]
    public string $createdAt;
}
