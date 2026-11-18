<?php

namespace Acme\Model;

use Bidirekt\PhpSdk\BidirektField;

trait Timestamps
{
    #[BidirektField]
    public string $createdAt;
}
