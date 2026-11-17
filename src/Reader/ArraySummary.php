<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

final class ArraySummary extends ExpressionSummary
{
    /**
     * @param list<ArrayItemSummary> $items
     */
    public function __construct(
        SourceLocation $location,
        public array $items,
    ) {
        parent::__construct($location);
    }
}
