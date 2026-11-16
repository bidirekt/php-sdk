<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\RelativePathToAbsolute;
use PHPUnit\Framework\TestCase;

final class RelativePathToAbsoluteTest extends TestCase
{
    public function testJoinsARelativePathToTheBase(): void
    {
        self::assertSame('/app/tests', RelativePathToAbsolute::relativePathToAbsolute('/app', 'tests/'));
        self::assertSame('/app/dev/helpers.php', RelativePathToAbsolute::relativePathToAbsolute('/app', './dev//helpers.php'));
        self::assertSame('/libs', RelativePathToAbsolute::relativePathToAbsolute('/app', '../libs'));
    }

    public function testKeepsAnAbsolutePath(): void
    {
        self::assertSame('/opt/vendor', RelativePathToAbsolute::relativePathToAbsolute('/app', '/opt/vendor/'));
    }
}
