<?php

declare(strict_types=1);

namespace Acme\Api\Pets;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use Spatie\DataTransferObject\DataTransferObject;

#[BidirektSchema]
final class NewPet extends DataTransferObject
{
    #[BidirektField]
    public string $name;

    #[BidirektField(optional: true)]
    public ?string $nickname = null;

    /** @var list<string> */
    #[BidirektField(optional: true)]
    public array $tags = [];
}
