<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Error;
use PhpToken;

/**
 * Tells a PHP-Parser error as PHP tells it, with what the file writes where the parser stopped, and the
 * `*` + `/` of a path that ends a `@bidirekt` docblock early as that mistake.
 */
final class ParserErrorToDiagnostic
{
    private const FIX = 'fix the code so PHP can parse it';

    /** PHP's own names of the tokens it shows with the text the file writes */
    private const VALUE_TOKENS = [
        'T_STRING' => 'identifier',
        'T_VARIABLE' => 'variable',
        'T_LNUMBER' => 'integer',
        'T_DNUMBER' => 'floating-point number',
        'T_NAME_FULLY_QUALIFIED' => 'fully qualified name',
        'T_NAME_QUALIFIED' => 'namespaced name',
        'T_NAME_RELATIVE' => 'namespace-relative name',
        'T_ENCAPSED_AND_WHITESPACE' => 'string content',
        'T_START_HEREDOC' => 'heredoc start',
        'T_END_HEREDOC' => 'heredoc end',
        'T_NUM_STRING' => 'number',
        'T_STRING_VARNAME' => 'variable name',
    ];

    /** PHP's own names of the tokens it shows without their text */
    private const NAMED_TOKENS = [
        'EOF' => 'end of file',
        'T_CONSTANT_ENCAPSED_STRING' => 'quoted string',
        "'\"'" => 'double-quote mark',
    ];

    /** the text of the tokens whose name is not the keyword they write */
    private const TOKEN_TEXTS = [
        'T_VOID_CAST' => '(void)', 'T_INT_CAST' => '(int)', 'T_DOUBLE_CAST' => '(double)', 'T_STRING_CAST' => '(string)',
        'T_ARRAY_CAST' => '(array)', 'T_OBJECT_CAST' => '(object)', 'T_BOOL_CAST' => '(bool)', 'T_UNSET_CAST' => '(unset)',
        'T_LOGICAL_OR' => 'or', 'T_LOGICAL_XOR' => 'xor', 'T_LOGICAL_AND' => 'and', 'T_YIELD_FROM' => 'yield from',
        'T_DOUBLE_ARROW' => '=>', 'T_PLUS_EQUAL' => '+=', 'T_MINUS_EQUAL' => '-=', 'T_MUL_EQUAL' => '*=', 'T_DIV_EQUAL' => '/=',
        'T_CONCAT_EQUAL' => '.=', 'T_MOD_EQUAL' => '%=', 'T_AND_EQUAL' => '&=', 'T_OR_EQUAL' => '|=', 'T_XOR_EQUAL' => '^=',
        'T_SL_EQUAL' => '<<=', 'T_SR_EQUAL' => '>>=', 'T_POW_EQUAL' => '**=', 'T_COALESCE_EQUAL' => '??=', 'T_COALESCE' => '??',
        'T_BOOLEAN_OR' => '||', 'T_BOOLEAN_AND' => '&&', 'T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG' => '&',
        'T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG' => '&', 'T_IS_EQUAL' => '==', 'T_IS_NOT_EQUAL' => '!=', 'T_IS_IDENTICAL' => '===',
        'T_IS_NOT_IDENTICAL' => '!==', 'T_SPACESHIP' => '<=>', 'T_IS_SMALLER_OR_EQUAL' => '<=', 'T_IS_GREATER_OR_EQUAL' => '>=',
        'T_PIPE' => '|>', 'T_SL' => '<<', 'T_SR' => '>>', 'T_INC' => '++', 'T_DEC' => '--', 'T_POW' => '**',
        'T_OBJECT_OPERATOR' => '->', 'T_NULLSAFE_OBJECT_OPERATOR' => '?->', 'T_PAAMAYIM_NEKUDOTAYIM' => '::',
        'T_NS_SEPARATOR' => '\\', 'T_ELLIPSIS' => '...', 'T_ATTRIBUTE' => '#[', 'T_CURLY_OPEN' => '{$',
        'T_DOLLAR_OPEN_CURLY_BRACES' => '${', 'T_PUBLIC_SET' => 'public(set)', 'T_PROTECTED_SET' => 'protected(set)',
        'T_PRIVATE_SET' => 'private(set)', 'T_HALT_COMPILER' => '__halt_compiler', 'T_CLASS_C' => '__CLASS__',
        'T_TRAIT_C' => '__TRAIT__', 'T_METHOD_C' => '__METHOD__', 'T_FUNC_C' => '__FUNCTION__', 'T_PROPERTY_C' => '__PROPERTY__',
        'T_LINE' => '__LINE__', 'T_FILE' => '__FILE__', 'T_DIR' => '__DIR__', 'T_NS_C' => '__NAMESPACE__',
    ];

    /** PHP shows this many characters of a token, then "..." */
    private const SHOWN_LENGTH = 30;

    public static function errorToDiagnostic(Error $error, string $path, string $code): Diagnostic
    {
        $position = $error->getAttributes()['startFilePos'] ?? null;
        if (is_int($position)) {
            $cutPath = self::cutPathToDiagnostic($path, $code, $position);
            if ($cutPath !== null) {
                return $cutPath;
            }
        }

        return new Diagnostic(
            'syntax error: ' . self::errorToText($error, $code) . ': ' . self::FIX,
            new SourceLocation($path, max(1, $error->getStartLine())),
        );
    }

    /**
     * The `*` + `/` of a path like `/owners/*` + `/pets` ends the docblock the path is written in, and PHP
     * stops at the rest of the docblock, read as code. Found when the last docblock before the error ends
     * a `@bidirekt` line with that path cut, the code goes on right after it, and the error comes before
     * the code that follows the docblock's own end.
     */
    private static function cutPathToDiagnostic(string $path, string $code, int $position): ?Diagnostic
    {
        $comment = null;
        foreach (PhpToken::tokenize($code) as $token) {
            if ($token->pos >= $position) {
                break;
            }
            if ($token->id === T_DOC_COMMENT) {
                $comment = $token;
            }
        }
        if ($comment === null) {
            return null;
        }
        $lines = explode("\n", $comment->text);
        $end = $comment->pos + strlen($comment->text);
        $start = preg_match('/@bidirekt\s.*\s(\S+)\*\/$/', $lines[count($lines) - 1], $before);
        $rest = preg_match('/\G\S+/', $code, $after, 0, $end);
        $docblockEnd = preg_match('/\*\/\s*/', $code, $ending, PREG_OFFSET_CAPTURE, $end + strlen($after[0] ?? ''));
        if ($start !== 1 || $rest !== 1 || $docblockEnd !== 1 || $position > $ending[0][1] + strlen($ending[0][0])) {
            return null;
        }
        $written = $before[1] . '*/' . $after[0];

        return new Diagnostic(
            sprintf('"*/" in "%s" ends the docblock: write "%s"', $written, str_replace('*/', '*\/', $written)),
            new SourceLocation($path, $comment->line + count($lines) - 1),
        );
    }

    private static function errorToText(Error $error, string $code): string
    {
        $message = $error->getRawMessage();
        if (preg_match('/^Syntax error, unexpected (\S+)(?:, expecting (.+))?$/', $message, $match) !== 1) {
            return lcfirst(rtrim($message, '.'));
        }
        $start = $error->getAttributes()['startFilePos'] ?? 0;
        $end = $error->getAttributes()['endFilePos'] ?? -1;
        $written = '';
        if (is_int($start) && is_int($end)) {
            $written = substr($code, $start, $end - $start + 1);
        }
        $text = 'unexpected ' . self::unexpectedToText($match[1], $written);
        if (isset($match[2])) {
            $text .= ', expecting ' . implode(' or ', array_map(static fn(string $name): string => self::expectedToText($name), explode(' or ', $match[2])));
        }

        return $text;
    }

    private static function unexpectedToText(string $name, string $written): string
    {
        if (isset(self::VALUE_TOKENS[$name])) {
            return sprintf('%s "%s"', self::VALUE_TOKENS[$name], self::writtenToShown(rtrim($written, "\r\n")));
        }
        if ($name === 'T_CONSTANT_ENCAPSED_STRING') {
            $quote = 'double';
            if (str_starts_with(ltrim($written, 'bB'), "'")) {
                $quote = 'single';
            }

            return sprintf('%s-quoted string "%s"', $quote, self::writtenToShown(substr(ltrim($written, 'bB'), 1, -1)));
        }
        if (isset(self::NAMED_TOKENS[$name])) {
            return self::NAMED_TOKENS[$name];
        }

        return sprintf('token "%s"', self::writtenToShown($written));
    }

    private static function expectedToText(string $name): string
    {
        if (isset(self::VALUE_TOKENS[$name])) {
            return self::VALUE_TOKENS[$name];
        }
        if (isset(self::NAMED_TOKENS[$name])) {
            return self::NAMED_TOKENS[$name];
        }
        if (str_starts_with($name, "'")) {
            return sprintf('"%s"', substr($name, 1, -1));
        }

        return sprintf('"%s"', self::TOKEN_TEXTS[$name] ?? strtolower(substr($name, 2)));
    }

    private static function writtenToShown(string $written): string
    {
        if (strlen($written) <= self::SHOWN_LENGTH) {
            return $written;
        }

        return substr($written, 0, self::SHOWN_LENGTH) . '...';
    }
}
