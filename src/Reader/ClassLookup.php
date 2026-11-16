<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;

/**
 * Either the one declaration of a class, or the errors that keep it from being read. Neither means
 * the class is declared nowhere the reader looks, and the caller says what it needed it for.
 */
final class ClassLookup
{
    /**
     * @param list<Diagnostic> $errors
     */
    public function __construct(
        public ?ClassDeclaration $declaration,
        public array $errors = [],
    ) {}
}
