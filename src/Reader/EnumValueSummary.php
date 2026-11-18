<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * `Enum::Case->value`.
 */
final class EnumValueSummary extends ExpressionSummary
{
    public function __construct(
        SourceLocation $location,
        public ClassConstantSummary $case,
    ) {
        parent::__construct($location);
    }
}
