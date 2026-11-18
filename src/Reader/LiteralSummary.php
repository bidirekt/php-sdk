<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

final class LiteralSummary extends ExpressionSummary
{
    /**
     * @param string|null $intLiteral an integer as written, like `0200`
     */
    public function __construct(
        SourceLocation $location,
        public int|float|string|bool|null $value,
        public ?string $intLiteral = null,
    ) {
        parent::__construct($location);
    }
}
