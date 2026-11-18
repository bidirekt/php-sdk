<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * `new BidirektField(…)` written so that PHP resolves it to the current namespace: without its `use`,
 * or qualified without the leading `\`.
 */
final class UnimportedFieldSummary extends ExpressionSummary
{
    public function __construct(
        SourceLocation $location,
        public string $written,
        public string $class,
        public string $fix,
    ) {
        parent::__construct($location);
    }
}
