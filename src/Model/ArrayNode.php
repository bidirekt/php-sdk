<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class ArrayNode extends SchemaNode
{
    public function __construct(
        public SchemaNode $items,
    ) {}
}
