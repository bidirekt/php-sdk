<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;

/**
 * The `@bidirekt` tags of one docblock and the element PHP-Parser binds it to.
 */
final class TaggedComment
{
    /**
     * @param Node $element the statement, class-like, member, parameter or function the docblock is above
     * @param list<CommentTag> $tags in the order they are written
     */
    public function __construct(
        public Node $element,
        public array $tags,
        public ParsedFile $file,
    ) {}

    /**
     * @return list<CommentTag>
     */
    public function tagsWith(string ...$keywords): array
    {
        return array_values(array_filter($this->tags, static fn(CommentTag $tag): bool => in_array($tag->keyword, $keywords, true)));
    }

    public function hasHandler(): bool
    {
        return $this->tagsWith('provides', 'consumes') !== [];
    }
}
