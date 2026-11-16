<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class PrimitiveNode extends SchemaNode
{
    /**
     * @param 'string'|'integer'|'float'|'number'|'boolean' $type
     */
    public function __construct(
        public string $type,
    ) {}
}
