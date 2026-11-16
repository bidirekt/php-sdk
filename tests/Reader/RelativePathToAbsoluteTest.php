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

    public function testKeepsTheDriveOfAWindowsPathAsItsRoot(): void
    {
        self::assertSame('C:/app/tests', RelativePathToAbsolute::relativePathToAbsolute('C:\\app', 'tests\\'));
        self::assertSame('C:/libs', RelativePathToAbsolute::relativePathToAbsolute('C:\\app', '..\\..\\libs'));
        self::assertSame('D:/opt/vendor', RelativePathToAbsolute::relativePathToAbsolute('C:\\app', 'D:/opt/vendor'));
        self::assertSame('D:/opt/vendor', RelativePathToAbsolute::relativePathToAbsolute('C:\\app', 'D:\\opt\\vendor\\'));
    }

    public function testKeepsTheServerAndShareOfAUncPathAsItsRoot(): void
    {
        self::assertSame('//server/share/app/vendor', RelativePathToAbsolute::relativePathToAbsolute('\\\\server\\share\\app', '.\\vendor'));
        self::assertSame('//server/share/libs', RelativePathToAbsolute::relativePathToAbsolute('\\\\server\\share\\app', '..\\..\\libs'));
        self::assertSame('//server/share/vendor', RelativePathToAbsolute::relativePathToAbsolute('C:\\app', '\\\\server\\share\\vendor'));
    }
}
