<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * The classes `self` and `static` name in a field read for a schema: a trait's `self` is the class that
 * uses it, and an inherited field's `static` is the schema being read.
 */
final class ClassScope
{
    public function __construct(
        public ClassDeclaration $selfClass,
        public ClassDeclaration $staticClass,
    ) {}
}
