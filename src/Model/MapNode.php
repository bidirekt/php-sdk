<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class MapNode extends SchemaNode
{
    public function __construct(
        public SchemaNode $values,
    ) {}
}
