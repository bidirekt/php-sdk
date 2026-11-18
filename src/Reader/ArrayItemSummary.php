<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class ArrayItemSummary
{
    public function __construct(
        public ?ExpressionSummary $key,
        public ExpressionSummary $value,
    ) {}
}
