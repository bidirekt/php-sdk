<?php

declare(strict_types=1);

namespace Acme\Admin;

use Acme\Shared\Entity;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class AdminUser extends Entity
{
    #[BidirektField]
    public string $email;
}
