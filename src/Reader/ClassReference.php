<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * The value of `X::class`, kept apart from a string so the reader can tell `Pet::class` from `'Pet'`.
 */
final class ClassReference
{
    public function __construct(
        public string $class,
    ) {}
}
