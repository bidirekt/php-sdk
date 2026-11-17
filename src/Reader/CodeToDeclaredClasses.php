<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpToken;

/**
 * The named classes, interfaces, traits and enums a file declares, read from PHP's own tokens without parsing
 * it, so a name in a comment, a string or a heredoc is not taken for a declaration.
 */
final class CodeToDeclaredClasses
{
    /**
     * A class-like keyword, then whitespace or comments, then a name: a declaration, or the same words in a
     * comment, a string or a heredoc, which only the tokens tell apart.
     */
    private const CANDIDATE_PATTERN = '~\b(?:class|interface|trait|enum)(?:\s|/\*.*?\*/|(?://|#)[^\n]*)+([a-z_\x80-\xff][a-z0-9_\x80-\xff]*)~is';

    /**
     * The short names the file may declare a class-like under, read from its text: every one it declares, and
     * maybe others, at a fraction of the cost of its tokens.
     *
     * @return list<string> in lowercase
     */
    public static function codeToCandidateNames(string $code): array
    {
        preg_match_all(self::CANDIDATE_PATTERN, $code, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * @return list<DeclaredClass> in the order they are written
     */
    public static function codeToDeclaredClasses(string $path, string $code): array
    {
        $tokens = array_values(PhpToken::tokenize($code));
        $namespace = '';
        $declared = [];
        foreach (self::tokensToKeywordPositions($tokens, $code) as $position) {
            $name = self::nextToken($tokens, $position);
            if ($tokens[$position]->id === T_NAMESPACE) {
                $namespace = '';
                if ($name !== null && ($name->id === T_STRING || $name->id === T_NAME_QUALIFIED)) {
                    $namespace = $name->text . '\\';
                }

                continue;
            }
            if ($name !== null && $name->id === T_STRING) {
                $declared[] = new DeclaredClass($namespace . $name->text, new SourceLocation($path, $name->line));
            }
        }

        return $declared;
    }

    /**
     * The positions of the `namespace`, `class`, `interface`, `trait` and `enum` tokens, found by PHP's array
     * functions rather than a loop over every token, since a project has millions of them.
     *
     * @param list<PhpToken> $tokens
     * @return list<int> in order
     */
    private static function tokensToKeywordPositions(array $tokens, string $code): array
    {
        $ids = array_column($tokens, 'id');
        $positions = [];
        foreach ([T_NAMESPACE, T_CLASS, T_INTERFACE, T_TRAIT] as $id) {
            array_push($positions, ...array_keys($ids, $id, true));
        }
        if (stripos($code, 'enum') !== false) {
            // Before PHP 8.1, `enum` is not a keyword: its token is a name, recognized by its text.
            array_push($positions, ...array_keys(array_map('strtolower', array_column($tokens, 'text')), 'enum', true));
        }
        sort($positions);

        return $positions;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private static function nextToken(array $tokens, int $position): ?PhpToken
    {
        for ($next = $position + 1; isset($tokens[$next]); $next++) {
            if (!$tokens[$next]->isIgnorable()) {
                return $tokens[$next];
            }
        }

        return null;
    }
}
