<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Writer;

/**
 * Ports the scalar rules of the `yaml` npm library (stringifyString.js) for the TS writer's options
 * (lineWidth 0, so no folding), so both SDKs quote the same text the same way.
 */
final class StringToYaml
{
    private const PLAIN_FORBIDDEN = '/^[\n\t ,[\]{}#&*!|>\'"%@`]|^[?-]$|^[?-][ \t]|[\n:][ \t]|[ \t]\n|[\n\t ]#|[\n\t :]$/D';
    private const OTHER_CORE_TYPES = [
        '/^(?:~|[Nn]ull|NULL)?$/D',
        '/^(?:[Tt]rue|TRUE|[Ff]alse|FALSE)$/D',
        '/^0o[0-7]+$/D',
        '/^[-+]?[0-9]+$/D',
        '/^0x[0-9a-fA-F]+$/D',
        '/^(?:[-+]?\.(?:inf|Inf|INF)|\.nan|\.NaN|\.NAN)$/D',
        '/^[-+]?(?:\.[0-9]+|[0-9]+(?:\.[0-9]*)?)[eE][-+]?[0-9]+$/D',
        '/^[-+]?(?:\.[0-9]+|[0-9]+\.[0-9]*)$/D',
    ];
    private const DOUBLE_QUOTED_MIN_MULTI_LINE_LENGTH = 40;

    /**
     * $indent is the indentation of the scalar's own children (the pair's indentation plus one step).
     */
    public static function stringToYaml(string $value, string $indent, bool $implicitKey): string
    {
        if (preg_match('/[\x{0}-\x{8}\x{b}-\x{1f}\x{7f}-\x{9f}]/u', $value) === 1) {
            return self::stringToDoubleQuoted($value, $indent, $implicitKey);
        }

        return self::stringToPlain($value, $indent, $implicitKey);
    }

    public static function stringToDoubleQuoted(string $value, string $indent, bool $implicitKey): string
    {
        $json = self::stringToJson($value);
        $length = strlen($json);
        $multiLine = self::stringToUtf16Length($json) >= self::DOUBLE_QUOTED_MIN_MULTI_LINE_LENGTH;
        $str = '';
        $start = 0;
        for ($i = 0; $i < $length; ++$i) {
            $ch = $json[$i];
            if ($ch === ' ' && self::charAt($json, $i + 1) === '\\' && self::charAt($json, $i + 2) === 'n') {
                // a space before a line break is escaped so that it is not folded away
                $str .= substr($json, $start, $i - $start) . '\\ ';
                $i += 1;
                $start = $i;
                $ch = '\\';
            }
            if ($ch !== '\\') {
                continue;
            }
            $next = self::charAt($json, $i + 1);
            if ($next === 'u') {
                $str .= substr($json, $start, $i - $start) . self::unicodeEscapeToYaml(substr($json, $i + 2, 4));
                $i += 5;
                $start = $i + 1;
                continue;
            }
            if ($next !== 'n'
                || $implicitKey
                || self::charAt($json, $i + 2) === '"'
                || !$multiLine
            ) {
                $i += 1;
                continue;
            }
            // a line break is written as an empty line, which folding reads back as one line break
            $str .= substr($json, $start, $i - $start) . "\n\n";
            while (self::charAt($json, $i + 2) === '\\'
                && self::charAt($json, $i + 3) === 'n'
                && self::charAt($json, $i + 4) !== '"'
            ) {
                $str .= "\n";
                $i += 2;
            }
            $str .= $indent;
            if (self::charAt($json, $i + 2) === ' ') {
                $str .= '\\';
            }
            $i += 1;
            $start = $i + 1;
        }
        if ($start === 0) {
            return $json;
        }

        return $str . substr($json, $start);
    }

    public static function stringToUtf16Length(string $value): int
    {
        return (int) preg_match_all('/./su', $value) + (int) preg_match_all('/[\x{10000}-\x{10ffff}]/u', $value);
    }

    private static function stringToPlain(string $value, string $indent, bool $implicitKey): string
    {
        $multiLine = str_contains($value, "\n");
        if ($implicitKey && $multiLine) {
            return self::stringToQuoted($value, $indent, $implicitKey);
        }
        if (preg_match(self::PLAIN_FORBIDDEN, $value) === 1) {
            if ($implicitKey || !$multiLine) {
                return self::stringToQuoted($value, $indent, $implicitKey);
            }

            return self::stringToBlock($value, $indent, $implicitKey);
        }
        if ($multiLine) {
            return self::stringToBlock($value, $indent, $implicitKey);
        }
        foreach (self::OTHER_CORE_TYPES as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return self::stringToQuoted($value, $indent, $implicitKey);
            }
        }

        return $value;
    }

    private static function stringToQuoted(string $value, string $indent, bool $implicitKey): string
    {
        if (str_contains($value, '"') && !str_contains($value, "'")) {
            return self::stringToSingleQuoted($value, $indent, $implicitKey);
        }

        return self::stringToDoubleQuoted($value, $indent, $implicitKey);
    }

    private static function stringToSingleQuoted(string $value, string $indent, bool $implicitKey): string
    {
        if (($implicitKey && str_contains($value, "\n")) || preg_match('/[ \t]\n|\n[ \t]/', $value) === 1) {
            return self::stringToDoubleQuoted($value, $indent, $implicitKey);
        }

        return "'" . self::lineBreaksToIndented(str_replace("'", "''", $value), "\n" . $indent) . "'";
    }

    private static function stringToBlock(string $value, string $indent, bool $implicitKey): string
    {
        if (preg_match('/\n[\t ]+$/D', $value) === 1) {
            return self::stringToQuoted($value, $indent, $implicitKey);
        }
        $endStart = strlen($value);
        while ($endStart > 0 && str_contains("\n\t ", $value[$endStart - 1])) {
            --$endStart;
        }
        $end = substr($value, $endStart);
        $endLineBreak = strpos($end, "\n");
        $chomp = '';
        if ($endLineBreak === false) {
            $chomp = '-';
        } elseif ($value === $end || $endLineBreak !== strlen($end) - 1) {
            $chomp = '+';
        }
        if ($end !== '') {
            $value = substr($value, 0, $endStart);
            if (str_ends_with($end, "\n")) {
                $end = substr($end, 0, -1);
            }
            $end = (string) preg_replace('/(?<!\n)\n+(?!\n|$)/D', '$0' . $indent, $end);
        }
        $startsWithSpace = false;
        $startLineBreak = -1;
        for ($startEnd = 0; $startEnd < strlen($value); ++$startEnd) {
            if ($value[$startEnd] === ' ') {
                $startsWithSpace = true;
            } elseif ($value[$startEnd] === "\n") {
                $startLineBreak = $startEnd;
            } else {
                break;
            }
        }
        $start = substr($value, 0, $startLineBreak + 1);
        if ($start !== '') {
            $value = substr($value, strlen($start));
            $start = self::lineBreaksToIndented($start, $indent);
        }
        $header = $chomp;
        if ($startsWithSpace) {
            $header = '2' . $chomp;
        }

        return '|' . $header . "\n" . $indent . $start . self::lineBreaksToIndented($value, $indent) . $end;
    }

    private static function lineBreaksToIndented(string $value, string $indent): string
    {
        return (string) preg_replace('/\n+/', '$0' . $indent, $value);
    }

    /**
     * What JavaScript's JSON.stringify writes for a string: only the quote, the backslash and the C0 controls
     * are escaped. Invalid UTF-8 becomes U+FFFD, as it does when the TS SDK reads the file as UTF-8.
     */
    private static function stringToJson(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function unicodeEscapeToYaml(string $code): string
    {
        $escapes = ['0000' => '\\0', '0007' => '\\a', '000b' => '\\v', '001b' => '\\e', '0085' => '\\N', '00a0' => '\\_', '2028' => '\\L', '2029' => '\\P'];
        if (isset($escapes[$code])) {
            return $escapes[$code];
        }
        if (str_starts_with($code, '00')) {
            return '\\x' . substr($code, 2);
        }

        return '\\u' . $code;
    }

    private static function charAt(string $value, int $index): string
    {
        return $value[$index] ?? '';
    }
}
