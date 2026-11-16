<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;

/**
 * Reads the `@bidirekt` tags of every `/** *\/` docblock bound to a statement, a declaration, a parameter
 * or a function, the element below it, its attributes included. Only a tag at the start of a docblock line
 * counts, outside ``` fences and `@example` blocks, and a tag's arguments run until the next tag, a fence
 * or the end of the docblock. A docblock with tags bound to no element is warned about.
 */
final class FilesToTaggedComments
{
    private const ARGUMENT_PATTERN = '/description="[^"]*"(?=\s|$)|union=\{[^}]*\}\S*|\S+/';

    private const LINE_START_PATTERN = '/^\s*(?:\/\*\*)?\s*\**\s*/';

    private const LINE_PREFIX_PATTERN = '/^\s*(?:\/\*\*)?\s*\**\s*$/';

    private const MENTION_PATTERN = '/(?<![^\s*])(@bidirekt[^\s*]*)[ \t]*([^\s*]*)/i';

    /**
     * @param list<ParsedFile> $files
     */
    public static function filesToTaggedComments(array $files): TaggedComments
    {
        $read = new TaggedComments();
        foreach ($files as $file) {
            self::fileToTaggedComments($file, $read);
        }

        return $read;
    }

    private static function fileToTaggedComments(ParsedFile $file, TaggedComments $read): void
    {
        $bound = [];
        foreach ((new NodeFinder())->find($file->statements, static fn(Node $node): bool => $node->getComments() !== []) as $node) {
            $element = self::nodeToElement($node);
            if ($element === null) {
                continue;
            }
            foreach ($node->getComments() as $comment) {
                if (!$comment instanceof Doc || isset($bound[$comment->getStartFilePos()]) || stripos($comment->getText(), '@bidirekt') === false) {
                    continue;
                }
                $bound[$comment->getStartFilePos()] = true;
                $tags = self::docToTags(new DocText($comment->getText(), $comment->getStartLine()), $file, $read);
                if ($tags !== []) {
                    $read->comments[] = new TaggedComment($element, $tags, $file);
                }
            }
        }
        foreach ($file->tokens as $token) {
            if ($token->id === T_DOC_COMMENT && !isset($bound[$token->pos]) && stripos($token->text, '@bidirekt') !== false) {
                self::warnUnreadDoc(new DocText($token->text, $token->line), $file, $read);
            }
        }
    }

    /**
     * The element a docblock declares, null when it is above none: a docblock in the middle of an
     * expression or at the end of a file or a class. One written before a closure passed to a call binds
     * to the argument: the element is the closure.
     */
    private static function nodeToElement(Node $node): ?Node
    {
        if ($node instanceof Arg && $node->value instanceof FunctionLike) {
            return $node->value;
        }
        if ($node instanceof Stmt\Nop) {
            return null;
        }
        if ($node instanceof Stmt || $node instanceof Param || $node instanceof FunctionLike) {
            return $node;
        }

        return null;
    }

    /**
     * @return list<CommentTag>
     */
    private static function docToTags(DocText $doc, ParsedFile $file, TaggedComments $read): array
    {
        $tags = [];
        $examples = [];
        $exampleStart = null;
        foreach (self::docToWrittenTags($doc) as [$written, $text, $position]) {
            $isOurs = str_starts_with(strtolower($written), '@bidirekt');
            $isFollowedBySpace = preg_match('/^@[\w$]+$/', $written) === 1;
            if ($exampleStart !== null) {
                if ($isOurs || !$isFollowedBySpace) {
                    continue;
                }
                $examples[] = [$exampleStart, $position];
                $exampleStart = null;
            }
            if (!$isOurs) {
                if ($written === '@example' && trim($text) === '') {
                    $exampleStart = $position + strlen($written);
                }

                continue;
            }
            if (self::namesPackageOrAttribute($written)) {
                continue;
            }
            $location = $file->lineToLocation($doc->positionToLine($position));
            if ($written !== '@bidirekt') {
                $read->warnings[] = new Diagnostic(sprintf('"%s" is not a @bidirekt tag: write "@bidirekt <keyword>"', $written), $location);

                continue;
            }
            $tag = self::textToTag($text, $location, $read);
            if ($tag !== null) {
                $tags[] = $tag;
            }
        }
        if ($exampleStart !== null) {
            $examples[] = [$exampleStart, strlen($doc->text)];
        }
        self::warnSkippedTags($doc, $examples, $file, $read);

        return $tags;
    }

    private static function textToTag(string $text, SourceLocation $location, TaggedComments $read): ?CommentTag
    {
        $words = self::textToArguments($text);
        $keyword = array_shift($words);
        if ($keyword === null) {
            $read->errors[] = new Diagnostic(sprintf('"@bidirekt" needs a keyword: use %s', NamesToChoice::namesToChoice(CommentTag::KEYWORDS)), $location);

            return null;
        }
        if (!in_array($keyword, CommentTag::KEYWORDS, true)) {
            $read->errors[] = new Diagnostic(sprintf('unknown tag "@bidirekt %s": use %s', $keyword, NamesToChoice::namesToChoice(CommentTag::KEYWORDS)), $location);

            return null;
        }

        return self::argumentsToTag($keyword, $words, $location);
    }

    /**
     * The words of a tag up to a fence: `description="…"` and `union={…}` are one word each, and the
     * escape `*\/` stands for the end-of-comment mark, which would end the docblock.
     *
     * @return list<string>
     */
    private static function textToArguments(string $text): array
    {
        $beforeFence = explode('```', $text)[0];
        preg_match_all(self::ARGUMENT_PATTERN, str_replace('*\/', '*/', $beforeFence), $matches);

        return $matches[0];
    }

    /**
     * Each tag written at the start of a line outside a fence: how it is written (`@bidirekt`, `@param`…),
     * the text after it up to the next tag, and its position in the docblock.
     *
     * @return list<array{string, string, int}>
     */
    private static function docToWrittenTags(DocText $doc): array
    {
        $tags = [];
        $last = count($doc->lines) - 1;
        foreach ($doc->lines as $index => [$offset, $line]) {
            if ($index === $last) {
                $line = (string) preg_replace('/\*\/$/', '', $line);
            }
            preg_match(self::LINE_START_PATTERN, $line, $prefix);
            $content = substr($line, strlen($prefix[0] ?? ''));
            $position = $offset + strlen($prefix[0] ?? '');
            if (preg_match('/^(@[^\s*]+)(.*)$/s', $content, $match) === 1 && !$doc->isInFence($position)) {
                $tags[] = [$match[1], $match[2], $position];

                continue;
            }
            $previous = array_pop($tags);
            if ($previous !== null) {
                $tags[] = [$previous[0], $previous[1] . "\n" . $content, $previous[2]];
            }
        }

        return $tags;
    }

    /**
     * Warns of the `@bidirekt <keyword>` written where it is not read: inside an `@example` or in the
     * middle of a line.
     *
     * @param list<array{int, int}> $examples
     */
    private static function warnSkippedTags(DocText $doc, array $examples, ParsedFile $file, TaggedComments $read): void
    {
        foreach (self::docToMentions($doc) as [$written, $next, $position]) {
            if ($written !== '@bidirekt' || $doc->isInFence($position)) {
                continue;
            }
            $atLineStart = self::startsItsLine($doc, $position);
            $isKeyword = in_array($next, CommentTag::KEYWORDS, true);
            $shown = trim($written . ' ' . $next);
            $location = $file->lineToLocation($doc->positionToLine($position));
            if (DocText::isInside($examples, $position)) {
                if ($atLineStart || $isKeyword) {
                    $read->warnings[] = new Diagnostic(sprintf('"%s" is inside an @example, so it is not read: move it above the @example', $shown), $location);
                }

                continue;
            }
            if (!$atLineStart && $isKeyword) {
                $read->warnings[] = new Diagnostic(
                    sprintf('"%s" is not at the start of a comment line, so it is not read: start the line with it', $shown),
                    $location,
                );
            }
        }
    }

    private static function warnUnreadDoc(DocText $doc, ParsedFile $file, TaggedComments $read): void
    {
        foreach (self::docToMentions($doc) as [$written, , $position]) {
            if (!self::startsItsLine($doc, $position) || $doc->isInFence($position) || self::namesPackageOrAttribute($written)) {
                continue;
            }
            $read->warnings[] = new Diagnostic(
                'this comment is not above a statement or declaration, so its "@bidirekt" tags are not read: move it above the statement or declaration, before its attributes',
                $file->lineToLocation($doc->positionToLine($position)),
            );

            return;
        }
    }

    /**
     * Every `@bidirekt…` written after a space or a `*`, whatever its case: how it is written, the word
     * after it and its position.
     *
     * @return list<array{string, string, int}>
     */
    private static function docToMentions(DocText $doc): array
    {
        preg_match_all(self::MENTION_PATTERN, $doc->text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $mentions = [];
        foreach ($matches as $match) {
            $mentions[] = [$match[1][0], $match[2][0], $match[1][1]];
        }

        return $mentions;
    }

    private static function startsItsLine(DocText $doc, int $position): bool
    {
        $lineStart = $doc->positionToLineStart($position);

        return preg_match(self::LINE_PREFIX_PATTERN, substr($doc->text, $lineStart, $position - $lineStart)) === 1;
    }

    /**
     * Whether the tag names the package (`@bidirekt/php-sdk`) or an SDK attribute (`@BidirektField`) in prose.
     */
    private static function namesPackageOrAttribute(string $written): bool
    {
        $name = preg_split('/[^\w$]/', substr($written, 1))[0] ?? '';

        return str_starts_with($written, '@bidirekt/') || in_array($name, SdkAttributes::shortNames(), true);
    }

    /**
     * @param list<string> $arguments
     */
    private static function argumentsToTag(string $keyword, array $arguments, SourceLocation $location): CommentTag
    {
        $tag = new CommentTag($keyword, $arguments, $location);
        foreach ($arguments as $argument) {
            if (str_contains($argument, '"') && preg_match('/^description="[^"]*"$/', $argument) !== 1) {
                $tag->error = new Diagnostic(
                    sprintf('cannot read "%s": remove the quotes, only description="<text>" takes them', $tag->tagToText()),
                    $location,
                );
            }
        }

        return $tag;
    }
}
