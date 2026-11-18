<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\HandlersToResources;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\TestCase;

final class HandlersToResourcesTest extends TestCase
{
    private const LOCATION_FILE = 'pets.php';

    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/php-sdk-handlers-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testFindsEveryKindOfHandlerInWrittenOrder(): void
    {
        file_put_contents($this->file, <<<'PHP'
            <?php

            trait FindsPets
            {
                public function find(): void {}
            }

            function residents(): array
            {
                return [];
            }

            $app->get('/pets', function () {
                return #[Route] fn () => null;
            });
            PHP);
        $parsed = (new PhpFileToParsed())->codeToParsed($this->file, (string) file_get_contents($this->file));
        self::assertInstanceOf(ParsedFile::class, $parsed);

        $handlers = HandlersToResources::fileToHandlers($parsed);

        self::assertSame(
            [ClassMethod::class, Function_::class, Closure::class, ArrowFunction::class],
            array_map(static fn(object $handler): string => get_class($handler), $handlers),
        );
    }

    public function testAcceptsAnUppercaseMethod(): void
    {
        self::assertNull(HandlersToResources::methodToUppercaseError('PATCH', new SourceLocation(self::LOCATION_FILE, 3)));
    }

    public function testRejectsAMethodNotInUppercase(): void
    {
        self::assertEquals(
            new Diagnostic('method "Post" must be uppercase: write "POST"', new SourceLocation(self::LOCATION_FILE, 3)),
            HandlersToResources::methodToUppercaseError('Post', new SourceLocation(self::LOCATION_FILE, 3)),
        );
    }

    public function testAcceptsAStatusWrittenAsANumber(): void
    {
        self::assertNull(HandlersToResources::statusToNumberError('204', new SourceLocation(self::LOCATION_FILE, 3)));
    }

    public function testRejectsAStatusThatIsNotANumber(): void
    {
        self::assertEquals(
            new Diagnostic('response status "2xx" must be a number', new SourceLocation(self::LOCATION_FILE, 3)),
            HandlersToResources::statusToNumberError('2xx', new SourceLocation(self::LOCATION_FILE, 3)),
        );
    }

    public function testRejectsAStatusWithALeadingZero(): void
    {
        self::assertEquals(
            new Diagnostic('response status "0200" must be written as 200', new SourceLocation(self::LOCATION_FILE, 3)),
            HandlersToResources::statusToNumberError('0200', new SourceLocation(self::LOCATION_FILE, 3)),
        );
    }
}
