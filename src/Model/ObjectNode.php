<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class ObjectNode extends SchemaNode
{
    /**
     * @param list<Property> $properties
     */
    public function __construct(
        public array $properties,
    ) {}
}
