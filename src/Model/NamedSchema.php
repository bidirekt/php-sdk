<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class NamedSchema
{
    public function __construct(
        public string $name,
        public SchemaNode $node,
        public SourceLocation $location,
    ) {}
}
