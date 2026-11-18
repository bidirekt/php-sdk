<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

abstract class SchemaNode
{
    public bool $optional = false;
    public bool $nullable = false;
    public ?string $description = null;
}
