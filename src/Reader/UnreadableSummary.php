<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * An expression that cannot be read without running the code, or an argument or array item not written in place.
 */
final class UnreadableSummary extends ExpressionSummary
{
    public function __construct(
        SourceLocation $location,
        public string $text,
    ) {
        parent::__construct($location);
    }
}
