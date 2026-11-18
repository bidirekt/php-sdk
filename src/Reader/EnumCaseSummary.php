<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class EnumCaseSummary
{
    public function __construct(
        public string $name,
        public ?ExpressionSummary $value,
    ) {}
}
