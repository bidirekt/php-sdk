<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\DescriptionToError;
use PHPUnit\Framework\TestCase;

final class DescriptionToErrorTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public function keptProvider(): array
    {
        return [
            'one word' => ['Weight'],
            'words' => ['In kilograms'],
            'digits' => ['ISO 8601'],
            'a YAML core word' => ['null'],
            'a hexadecimal number' => ['0x1F'],
        ];
    }

    /**
     * @dataProvider keptProvider
     */
    public function testKeepsAsciiLettersAndDigitsWithSingleSpacesBetweenWords(string $description): void
    {
        self::assertNull(DescriptionToError::descriptionToError($description));
    }

    /**
     * @return array<string, array{string}>
     */
    public function brokenProvider(): array
    {
        return [
            'punctuation' => ['In kilograms, rounded.'],
            'a leading space' => [' Weight'],
            'a trailing space' => ['Weight '],
            'two spaces' => ['In  kilograms'],
            'a tab' => ["In\tkilograms"],
            'a line break' => ["In\nkilograms"],
            'a trailing line break' => ["Weight\n"],
            'an underscore' => ['birth_date'],
            'a letter outside ASCII' => ["Pr\u{e9}nom"],
        ];
    }

    /**
     * @dataProvider brokenProvider
     */
    public function testBreaksOnAnythingElse(string $description): void
    {
        self::assertNotNull(DescriptionToError::descriptionToError($description));
    }

    public function testSaysTheRuleWithTheDescriptionAsAJsonString(): void
    {
        self::assertSame(
            'description "In kilograms, rounded." must be ASCII letters and digits, with single spaces between words: remove or replace the other characters',
            DescriptionToError::descriptionToError('In kilograms, rounded.'),
        );
        self::assertSame(
            'description "seen at\nnoon, say \"hi\"" must be ASCII letters and digits, with single spaces between words: remove or replace the other characters',
            DescriptionToError::descriptionToError("seen at\nnoon, say \"hi\""),
        );
        self::assertSame(
            '"description" is empty: write a description or leave "description" out',
            DescriptionToError::descriptionToError(''),
        );
    }
}
