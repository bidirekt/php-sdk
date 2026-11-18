<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class RefNode extends SchemaNode
{
    public function __construct(
        public string $schema,
    ) {}
}
