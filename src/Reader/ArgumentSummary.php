<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

final class ArgumentSummary
{
    /**
     * @param ExpressionSummary|null $value null for an argument not written in place: unpacked, by reference or `...`
     */
    public function __construct(
        public ?string $name,
        public ?SourceLocation $nameLocation,
        public SourceLocation $location,
        public ?ExpressionSummary $value,
    ) {}
}
