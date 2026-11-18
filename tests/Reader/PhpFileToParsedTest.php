<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\ErrorHandler;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use RangeException;

final class PhpFileToParsedTest extends TestCase
{
    private const FILE = '/project/pet.php';

    public function testResolvesNamesAndKeepsTheOriginal(): void
    {
        $code = <<<'PHP'
            <?php

            namespace App;

            use Acme\Base as BaseModel;

            final class Pet extends BaseModel {}
            PHP;

        $parsed = (new PhpFileToParsed())->codeToParsed(self::FILE, $code);

        self::assertInstanceOf(ParsedFile::class, $parsed);
        $class = (new NodeFinder())->findFirstInstanceOf($parsed->statements, Class_::class);
        self::assertNotNull($class);
        self::assertSame('App\\Pet', (string) $class->namespacedName);
        self::assertNotNull($class->extends);
        self::assertSame('Acme\\Base', $class->extends->toString());
        $original = $class->extends->getAttribute('originalName');
        self::assertInstanceOf(Name::class, $original);
        self::assertSame('BaseModel', $original->toString());
    }

    public function testReadsCodeOfAnyPhpVersion(): void
    {
        $code = <<<'PHP'
            <?php

            enum Status: string
            {
                case Available = 'available';
            }

            final readonly class Pet
            {
                public function __construct(public Status $status = new Status()) {}
            }
            PHP;

        self::assertInstanceOf(ParsedFile::class, (new PhpFileToParsed())->codeToParsed(self::FILE, $code));
    }

    public function testTurnsASyntaxErrorIntoAnErrorAtItsLine(): void
    {
        $code = <<<'PHP'
            <?php

            final class Pet
            {
                public string $name;

                public string $broken =;
            }
            PHP;

        self::assertEquals(
            new Diagnostic("syntax error: unexpected ';': fix the code so PHP can parse it", new SourceLocation(self::FILE, 7)),
            (new PhpFileToParsed())->codeToParsed(self::FILE, $code),
        );
    }

    public function testTurnsAnUnclosedClassIntoAnErrorAtTheEndOfTheFile(): void
    {
        $code = "<?php\n\nfinal class Owner\n{\n    public string \$name;\n";

        self::assertEquals(
            new Diagnostic('syntax error: unexpected EOF: fix the code so PHP can parse it', new SourceLocation(self::FILE, 6)),
            (new PhpFileToParsed())->codeToParsed(self::FILE, $code),
        );
    }

    public function testTurnsATokenOfANewerPhpThanThePhpParserReadsIntoAnError(): void
    {
        $parser = new class implements Parser {
            public function parse(string $code, ?ErrorHandler $errorHandler = null): ?array
            {
                throw new RangeException('The lexer returned an invalid token (id=408, value=|>)');
            }

            public function getTokens(): array
            {
                return [];
            }
        };

        self::assertEquals(
            new Diagnostic(
                sprintf('cannot parse the file: nikic/php-parser does not read the syntax of PHP %d.%d yet: update it with composer update nikic/php-parser', PHP_MAJOR_VERSION, PHP_MINOR_VERSION),
                new SourceLocation(self::FILE, 1),
            ),
            (new PhpFileToParsed($parser))->codeToParsed(self::FILE, "<?php\n\$name = 'Rex' |> trim(...);\n"),
        );
    }

    public function testRejectsAFileItCannotRead(): void
    {
        self::assertEquals(
            new Diagnostic('cannot read the file: check that it exists and can be read', new SourceLocation(__DIR__ . '/missing.php', 1)),
            PhpFileToParsed::fileToCode(__DIR__ . '/missing.php'),
        );
    }
}
