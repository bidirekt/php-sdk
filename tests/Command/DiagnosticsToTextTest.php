<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Command;

use Bidirekt\PhpSdk\Command\DiagnosticsToText;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PHPUnit\Framework\TestCase;

final class DiagnosticsToTextTest extends TestCase
{
    public function testSortsErrorsAndWarningsTogetherByAbsoluteFileBytesThenLine(): void
    {
        $text = DiagnosticsToText::diagnosticsToText(
            [
                new Diagnostic('second error', new SourceLocation('/app/src/pet.php', 12)),
                new Diagnostic('first error', new SourceLocation('/app/src/Pet.php', 9)),
                new Diagnostic('error without a place'),
            ],
            [
                new Diagnostic('a warning', new SourceLocation('/app/src/pet.php', 3)),
                new Diagnostic('another warning', new SourceLocation('/app/src/Pet.php', 10)),
                new Diagnostic('a warning in the parent folder', new SourceLocation('/lib/Base.php', 1)),
            ],
            '/app',
        );

        self::assertSame(
            "error without a place\n"
            . "src/Pet.php:9 first error\n"
            . "src/Pet.php:10 warning: another warning\n"
            . "src/pet.php:3 warning: a warning\n"
            . "src/pet.php:12 second error\n"
            . "../lib/Base.php:1 warning: a warning in the parent folder\n",
            $text,
        );
    }

    public function testIsEmptyWithoutDiagnostics(): void
    {
        self::assertSame('', DiagnosticsToText::diagnosticsToText([], [], '/app'));
    }
}
