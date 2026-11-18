<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class UnionNode extends SchemaNode
{
    /**
     * @param list<UnionMember> $members
     */
    public function __construct(
        public array $members,
    ) {}
}
