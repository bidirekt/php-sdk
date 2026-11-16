<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node\Expr;

final class ClassConstant
{
    /**
     * @param ClassDeclaration $owner the class-like that declares the constant, which `self` in its value names
     */
    public function __construct(
        public Expr $value,
        public ClassDeclaration $owner,
    ) {}
}
