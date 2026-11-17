<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * `new BidirektField(…)`.
 */
final class NewFieldSummary extends ExpressionSummary
{
    /**
     * @param list<ArgumentSummary> $arguments
     */
    public function __construct(
        SourceLocation $location,
        public array $arguments,
    ) {
        parent::__construct($location);
    }
}
