<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * An expression of an attribute argument, a constant or an enum case, as written: it is evaluated once the
 * classes it names are known, and for each class `self`, `static` and `parent` stand for.
 */
abstract class ExpressionSummary
{
    public function __construct(
        public SourceLocation $location,
    ) {}
}
