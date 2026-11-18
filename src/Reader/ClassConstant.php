<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class ClassConstant
{
    /**
     * @param ClassDeclaration $owner the class-like that declares the constant, which `self` in its value names
     */
    public function __construct(
        public ExpressionSummary $value,
        public ClassDeclaration $owner,
    ) {}
}
