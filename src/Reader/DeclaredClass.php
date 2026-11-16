<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

final class DeclaredClass
{
    public function __construct(
        public string $name,
        public SourceLocation $location,
    ) {}
}
