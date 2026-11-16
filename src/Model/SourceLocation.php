<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class SourceLocation
{
    public function __construct(
        public string $file,
        public int $line,
    ) {}
}
