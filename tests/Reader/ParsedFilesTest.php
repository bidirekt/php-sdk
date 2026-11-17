<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\FileSummary;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\ParsedFiles;
use Bidirekt\PhpSdk\Reader\SchemaTags;
use PHPUnit\Framework\TestCase;

final class ParsedFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/php-sdk-parsed-files-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        $this->directory = (string) realpath($this->directory);
    }

    protected function tearDown(): void
    {
        foreach ((array) scandir($this->directory) as $entry) {
            if (is_string($entry) && is_file($this->directory . '/' . $entry)) {
                unlink($this->directory . '/' . $entry);
            }
        }
        rmdir($this->directory);
    }

    public function testParsesALookedUpFileOnceAndKeepsItsSummary(): void
    {
        file_put_contents($this->directory . '/pet.php', "<?php\n\nnamespace App;\n\nfinal class Pet {}\n");
        $files = new ParsedFiles();

        $summary = $files->pathToSummary($this->directory . '/pet.php');

        self::assertInstanceOf(FileSummary::class, $summary);
        self::assertSame('App\\Pet', $summary->classDeclarationNamed('app\\pet')?->name);
        unlink($this->directory . '/pet.php');
        self::assertSame($summary, $files->pathToSummary($this->directory . '/pet.php'));
    }

    public function testKeepsWhyAFileCannotBeRead(): void
    {
        file_put_contents($this->directory . '/broken.php', "<?php\n\nfinal class Broken {\n");
        $files = new ParsedFiles();

        $error = $files->pathToSummary($this->directory . '/broken.php');

        self::assertEquals(new Diagnostic('syntax error: unexpected end of file: fix the code so PHP can parse it', new SourceLocation($this->directory . '/broken.php', 4)), $error);
        self::assertSame($error, $files->pathToSummary($this->directory . '/broken.php'));
    }

    public function testAnswersALookupOfAProjectFileWithTheSummaryOfItsRead(): void
    {
        file_put_contents($this->directory . '/owner.php', "<?php\n\n#[\\Bidirekt\\PhpSdk\\BidirektSchema]\nfinal class Owner {}\n");
        $files = new ParsedFiles();
        $read = $files->projectFileToRead($this->directory . '/owner.php');
        self::assertInstanceOf(ParsedFile::class, $read);

        $summary = $files->addProjectFile($read, new SchemaTags());

        self::assertSame($summary, $files->pathToSummary($this->directory . '/owner.php'));
    }
}
