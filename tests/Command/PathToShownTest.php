<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Command;

use Bidirekt\PhpSdk\Command\PathToShown;
use PHPUnit\Framework\TestCase;

final class PathToShownTest extends TestCase
{
    public function testShowsAPathRelativeToTheCwd(): void
    {
        self::assertSame('src/Pet.php', PathToShown::pathToShown('/app/src/Pet.php', '/app'));
        self::assertSame('../lib/Base.php', PathToShown::pathToShown('/lib/Base.php', '/app'));
        self::assertSame('', PathToShown::pathToShown('/app/', '/app'));
        self::assertSame('../..', PathToShown::pathToShown('/', '/app/api'));
    }
}
