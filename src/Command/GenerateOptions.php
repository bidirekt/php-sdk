<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

final class GenerateOptions
{
    public function __construct(
        public bool $check = false,
        public ?string $project = null,
        public ?string $out = null,
    ) {}
}
