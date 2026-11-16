<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use PHPUnit\Framework\TestCase;

final class BinTest extends TestCase
{
    public function testPrintsTheUsageWithoutTheGenerateCommand(): void
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../bin/php-sdk'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        self::assertSame('', stream_get_contents($pipes[1]));
        self::assertSame(
            "Usage: php-sdk generate [--check] [--project <dir>] [--out <file>]\n",
            stream_get_contents($pipes[2]),
        );
        self::assertSame(1, proc_close($process));
    }
}
