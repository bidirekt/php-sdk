<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class PhpFileToParsedTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-php-file-to-parsed-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (array_diff((array) scandir($this->root), ['.', '..']) as $entry) {
            unlink($this->root . '/' . $entry);
        }
        rmdir($this->root);
    }

    public function testResolvesNamesAndKeepsTheOriginal(): void
    {
        $file = $this->write('pet.php', <<<'PHP'
            <?php

            namespace App;

            use Acme\Base as BaseModel;

            final class Pet extends BaseModel {}
            PHP);

        $parsed = (new PhpFileToParsed())->fileToParsed($file);

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
        $file = $this->write('status.php', <<<'PHP'
            <?php

            enum Status: string
            {
                case Available = 'available';
            }

            final readonly class Pet
            {
                public function __construct(public Status $status = new Status()) {}
            }
            PHP);

        self::assertInstanceOf(ParsedFile::class, (new PhpFileToParsed())->fileToParsed($file));
    }

    public function testTurnsASyntaxErrorIntoAnErrorAtItsLine(): void
    {
        $file = $this->write('pet.php', <<<'PHP'
            <?php

            final class Pet
            {
                public string $name;

                public string $broken =;
            }
            PHP);

        self::assertEquals(
            new Diagnostic("syntax error: unexpected ';': fix the code so PHP can parse it", new SourceLocation($file, 7)),
            (new PhpFileToParsed())->fileToParsed($file),
        );
    }

    public function testTurnsAnUnclosedClassIntoAnErrorAtTheEndOfTheFile(): void
    {
        $file = $this->write('owner.php', "<?php\n\nfinal class Owner\n{\n    public string \$name;\n");

        self::assertEquals(
            new Diagnostic('syntax error: unexpected EOF: fix the code so PHP can parse it', new SourceLocation($file, 6)),
            (new PhpFileToParsed())->fileToParsed($file),
        );
    }

    public function testRejectsAFileItCannotRead(): void
    {
        self::assertEquals(
            new Diagnostic('cannot read the file: check that it exists and can be read', new SourceLocation($this->root . '/missing.php', 1)),
            (new PhpFileToParsed())->fileToParsed($this->root . '/missing.php'),
        );
    }

    private function write(string $name, string $code): string
    {
        file_put_contents($this->root . '/' . $name, $code);

        return $this->root . '/' . $name;
    }
}
