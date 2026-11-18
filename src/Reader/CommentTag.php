<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * One `@bidirekt <keyword> <arguments…>` line of a docblock.
 */
final class CommentTag
{
    public const KEYWORDS = ['provides', 'consumes', 'request', 'response', 'schema', 'field'];

    /**
     * @param string $keyword one of KEYWORDS
     * @param list<string> $arguments
     * @param Diagnostic|null $error why the tag cannot be read whatever its keyword, as quotes outside description="…"
     */
    public function __construct(
        public string $keyword,
        public array $arguments,
        public SourceLocation $location,
        public ?Diagnostic $error = null,
    ) {}

    /**
     * How messages show the tag: `@bidirekt provides GET /pets`.
     */
    public function tagToText(): string
    {
        return implode(' ', ['@bidirekt', $this->keyword, ...$this->arguments]);
    }
}
