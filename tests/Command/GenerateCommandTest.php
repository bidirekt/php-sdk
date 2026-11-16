<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Command;

use Bidirekt\PhpSdk\Command\CommandResult;
use Bidirekt\PhpSdk\Command\GenerateCommand;
use PHPUnit\Framework\TestCase;

final class GenerateCommandTest extends TestCase
{
    private const USAGE = "Usage: php-sdk generate [--check] [--project <dir>] [--out <file>]\n";

    private string $home;

    private string $root;

    private string $homeToRestore = 'HOME';

    /**
     * The project's parent folder is the home folder for the test, so it never reads a composer.json above it.
     */
    protected function setUp(): void
    {
        $home = sys_get_temp_dir() . '/php-sdk-generate-command-' . bin2hex(random_bytes(6));
        mkdir($home . '/project', 0777, true);
        $this->home = (string) realpath($home);
        $this->root = $this->home . '/project';
        $previous = getenv('HOME');
        if (is_string($previous)) {
            $this->homeToRestore = 'HOME=' . $previous;
        }
        putenv('HOME=' . $this->home);
    }

    protected function tearDown(): void
    {
        putenv($this->homeToRestore);
        self::removeDirectory($this->home);
    }

    public function testPrintsTheUsageWithoutTheGenerateCommand(): void
    {
        self::assertEquals(new CommandResult(1, self::USAGE), GenerateCommand::runCommand([], $this->root));
        self::assertFalse(file_exists($this->root . '/contracts'));
    }

    public function testPrintsTheUsageOnAnUnknownOption(): void
    {
        self::assertEquals(new CommandResult(1, self::USAGE), GenerateCommand::runCommand(['generate', '--watch'], $this->root));
        self::assertFalse(file_exists($this->root . '/contracts'));
    }

    public function testFailsOnAProjectThatDoesNotExist(): void
    {
        self::assertEquals(
            new CommandResult(1, "project \"apps/api\" not found\n"),
            GenerateCommand::runCommand(['generate', '--project', 'apps/api'], $this->root),
        );
        self::assertFalse(file_exists($this->root . '/contracts'));
    }

    public function testPrintsAComposerJsonErrorAsFileLineMessage(): void
    {
        $this->write('composer.json', "{\n");
        $this->write('src/Pet.php', "<?php\nfinal class Pet {}\n");

        self::assertEquals(
            new CommandResult(1, "composer.json:1 cannot read the file as JSON: fix composer.json\n"),
            GenerateCommand::runCommand(['generate'], $this->root),
        );
    }

    public function testFailsWithoutWritingWhenThereIsNoAnnotation(): void
    {
        $this->write('src/Pet.php', "<?php\nfinal class Pet {}\n");

        self::assertEquals(
            new CommandResult(1, "no bidirekt/php-sdk annotation found in \".\": nothing to generate\n"),
            GenerateCommand::runCommand(['generate'], $this->root),
        );
        self::assertFalse(file_exists($this->root . '/contracts'));
    }

    public function testNamesTheProjectFolderAndPrintsTheWarningsBeforeANothingToGenerate(): void
    {
        $this->write('apps/api/b.php', "<?php\nnamespace App;\n\nfinal class B\n{\n    #[BidirektField]\n    public int \$id;\n}\n");
        $this->write('apps/api/a.php', "<?php\nnamespace App;\n\n#[BidirektSchema]\nfinal class A {}\n");

        self::assertEquals(
            new CommandResult(
                1,
                "apps/api/a.php:4 warning: #[BidirektSchema] resolves to \"App\\BidirektSchema\", so it is not read: write use Bidirekt\\PhpSdk\\BidirektSchema\n"
                . "apps/api/b.php:6 warning: #[BidirektField] resolves to \"App\\BidirektField\", so it is not read: write use Bidirekt\\PhpSdk\\BidirektField\n"
                . "no bidirekt/php-sdk annotation found in \"apps/api\": nothing to generate\n",
            ),
            GenerateCommand::runCommand(['generate', '--project', 'apps/api/'], $this->root),
        );
    }

    public function testPrintsTheSyntaxErrorsSortedAndWritesNothing(): void
    {
        $this->write('pet.php', "<?php\n\nfinal class Pet\n{\n    public string \$broken =;\n}\n");
        $this->write('owner.php', "<?php\n\nfinal class Owner\n{\n");

        self::assertEquals(
            new CommandResult(
                1,
                "owner.php:5 syntax error: unexpected EOF: fix the code so PHP can parse it\n"
                . "pet.php:5 syntax error: unexpected ';': fix the code so PHP can parse it\n",
            ),
            GenerateCommand::runCommand(['generate', '--check'], $this->root),
        );
        self::assertFalse(file_exists($this->root . '/contracts'));
    }

    private static function removeDirectory(string $directory): void
    {
        foreach (array_diff((array) scandir($directory), ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                self::removeDirectory($path);

                continue;
            }
            unlink($path);
        }
        rmdir($directory);
    }

    private function write(string $path, string $content): void
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, $content);
    }
}
