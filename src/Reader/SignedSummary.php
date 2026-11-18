<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * `-X` or `+X`.
 */
final class SignedSummary extends ExpressionSummary
{
    public function __construct(
        SourceLocation $location,
        public string $text,
        public bool $negative,
        public ExpressionSummary $operand,
    ) {
        parent::__construct($location);
    }
}
