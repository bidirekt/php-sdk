<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class Body
{
    public function __construct(
        public ?string $schema,
        public SourceLocation $location,
    ) {}
}
