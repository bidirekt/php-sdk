<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class Diagnostic
{
    public function __construct(
        public string $message,
        public ?SourceLocation $location = null,
    ) {}
}
