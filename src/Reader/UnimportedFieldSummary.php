<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * `new BidirektField(…)` without the `use` for it, so PHP resolves it to the current namespace.
 */
final class UnimportedFieldSummary extends ExpressionSummary
{
    public function __construct(
        SourceLocation $location,
        public string $class,
    ) {
        parent::__construct($location);
    }
}
