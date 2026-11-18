<?php

declare(strict_types=1);

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektField;

abstract class Entity
{
    #[BidirektField]
    public int $id;

    #[BidirektField(description: 'ISO 8601 date and time')]
    public string $createdAt;
}
