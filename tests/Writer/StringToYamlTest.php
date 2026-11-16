<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Writer;

use Bidirekt\PhpSdk\Writer\StringToYaml;
use PHPUnit\Framework\TestCase;

final class StringToYamlTest extends TestCase
{
    private const INDENT = '    ';

    /**
     * @return array<string, array{string, string}>
     */
    public function valueProvider(): array
    {
        return [
            'plain text' => ['plain text', 'plain text'],
            'core null word' => ['null', '"null"'],
            'tilde' => ['~', '"~"'],
            'empty' => ['', '""'],
            'core boolean' => ['TRUE', '"TRUE"'],
            'mixed-case boolean is a string' => ['tRue', 'tRue'],
            'YAML 1.1 boolean is a string' => ['yes', 'yes'],
            'integer' => ['-123', '"-123"'],
            'octal' => ['0o17', '"0o17"'],
            'not octal' => ['0o8', '0o8'],
            'hexadecimal' => ['0x1F', '"0x1F"'],
            'exponent' => ['.5e+3', '".5e+3"'],
            'float with trailing dot' => ['5.', '"5."'],
            'not a number' => ['.NaN', '".NaN"'],
            'date is a string' => ['2001-12-14', '2001-12-14'],
            'colon and space' => ['a: b', '"a: b"'],
            'colon and tab' => ["a:\tb", "\"a:\\tb\""],
            'trailing colon' => ['x:', '"x:"'],
            'space and hash' => ['a #b', '"a #b"'],
            'tab and hash' => ["a\t#b", "\"a\\t#b\""],
            'lone dash' => ['-', '"-"'],
            'lone question mark' => ['?', '"?"'],
            'dash and tab' => ["-\tx", "\"-\\tx\""],
            'dash without space' => ['-x', '-x'],
            'leading indicator' => ['%x', '"%x"'],
            'tab inside' => ["a\tb", "a\tb"],
            'leading tab' => ["\tx", "\"\\tx\""],
            'trailing tab' => ["x\t", "\"x\\t\""],
            'double quotes only' => ['"x', '\'"x\''],
            'single quote only' => ["'x", '"\'x"'],
            'both quote kinds' => ['- both \' and "', '"- both \' and \\""'],
            'document start inside a nested value' => ['---', '---'],
            'non-breaking space' => ["x\u{a0}", "x\u{a0}"],
            'line separator' => ["\u{2028}", "\u{2028}"],
            'control character' => ["a\x01b", '"a\x01b"'],
            'null character' => ["a\x00b", '"a\0b"'],
            'escape character' => ["\x1b", '"\e"'],
            'delete is forced into double quotes but not escaped' => ["\x7f", "\"\x7f\""],
            'next line' => ["\u{85}x", "\"\u{85}x\""],
            'carriage return' => ["a\rb", '"a\rb"'],
            'slash and non-ASCII are not escaped' => ["a/\u{e9}: \x01", "\"a/\u{e9}: \\x01\""],
            'invalid UTF-8 is replaced' => ["\xff: x", "\"\u{fffd}: x\""],
            'line break' => ["a\nb", "|-\n    a\n    b"],
            'trailing line break' => ["a\n", "|\n    a"],
            'trailing line breaks' => ["a\n\n", "|+\n    a\n"],
            'leading spaces' => ["  a\nb", "|2-\n      a\n    b"],
            'leading line breaks' => ["\n\na", "|-\n    \n\n    a"],
            'empty line inside' => ["a\n\nb", "|-\n    a\n\n    b"],
            'space before a line break' => ["a \nb", "|-\n    a \n    b"],
            'tab after a line break' => ["a\n\tb", "|-\n    a\n    \tb"],
            'whitespace-only last line' => ["a\nb\n\t", '"a\nb\n\t"'],
            'line break and double quotes' => ["\"q\"\nx", "|-\n    \"q\"\n    x"],
            'line break and control character' => ["short \x01\nx", '"short \x01\nx"'],
            'long text with a line break and a control character' => [
                "a long description \x01 that is longer than forty characters\nsecond",
                "\"a long description \\x01 that is longer than forty characters\n\n    second\"",
            ],
            'long text with a space around a line break' => [
                "a long description \x01 that is longer than forty characters \n second",
                "\"a long description \\x01 that is longer than forty characters\\ \n\n    \\ second\"",
            ],
        ];
    }

    /**
     * @dataProvider valueProvider
     */
    public function testWritesAValueAsTheYamlLibraryDoes(string $value, string $expected): void
    {
        self::assertSame($expected, StringToYaml::stringToYaml($value, self::INDENT, false));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function keyProvider(): array
    {
        return [
            'plain key' => ['birth_date', 'birth_date'],
            'path' => ['/pets/*', '/pets/*'],
            'core boolean' => ['true', '"true"'],
            'line break' => ["a\nb", '"a\nb"'],
            'long line break is not folded in a key' => [
                "a key that is much longer than forty characters\nsecond",
                '"a key that is much longer than forty characters\nsecond"',
            ],
            'line break and double quotes' => ["\"q\"\nx", '"\"q\"\nx"'],
            'leading space' => [' x', '" x"'],
        ];
    }

    /**
     * @dataProvider keyProvider
     */
    public function testWritesAKeyAsTheYamlLibraryDoes(string $key, string $expected): void
    {
        self::assertSame($expected, StringToYaml::stringToYaml($key, self::INDENT, true));
    }

    public function testCountsLengthInUtf16CodeUnits(): void
    {
        self::assertSame(4, StringToYaml::stringToUtf16Length("a\u{e9}\u{1f600}"));
    }
}
