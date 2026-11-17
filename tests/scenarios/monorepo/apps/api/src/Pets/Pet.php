<?php

declare(strict_types=1);

namespace Acme\Api\Pets;

use Acme\Shared\Entity;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet extends Entity
{
    #[BidirektField]
    public string $name;

    #[BidirektField(description: 'available, pending or sold')]
    public string $status = 'available';

    #[BidirektField]
    public ?string $nickname = null;

    /** @var list<string> */
    #[BidirektField]
    public array $tags = [];
}
