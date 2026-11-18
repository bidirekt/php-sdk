<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class UnionMember
{
    public function __construct(
        public string $key,
        public SchemaNode $node,
    ) {}
}
