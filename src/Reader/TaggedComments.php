<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;

/**
 * Every docblock of a file with `@bidirekt` tags, and the errors and warnings of reading the tags themselves.
 */
final class TaggedComments
{
    /**
     * @param list<TaggedComment> $comments in the order they are written
     * @param list<Diagnostic> $errors
     * @param list<Diagnostic> $warnings
     */
    public function __construct(
        public array $comments = [],
        public array $errors = [],
        public array $warnings = [],
    ) {}
}
