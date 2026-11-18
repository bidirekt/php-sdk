<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class EnumNode extends SchemaNode
{
    /**
     * @param list<string> $members
     */
    public function __construct(
        public array $members,
    ) {}
}
