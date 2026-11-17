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
        $this->write('pet.php', "<?php\n\n#[BidirektSchema]\nfinal class Pet\n{\n    public string \$broken =;\n}\n");
        $this->write('owner.php', "<?php\n\n#[BidirektSchema]\nfinal class Owner\n{\n");

        self::assertEquals(
            new CommandResult(
                1,
                "owner.php:6 syntax error: unexpected EOF: fix the code so PHP can parse it\n"
                . "pet.php:6 syntax error: unexpected ';': fix the code so PHP can parse it\n",
            ),
            GenerateCommand::runCommand(['generate', '--check'], $this->root),
        );
        self::assertFalse(file_exists($this->root . '/contracts'));
    }

    /**
     * A monolith: 300 endpoints over 400 schemas of 10 fields each, beside 2000 files that mention no annotation.
     * Each file is parsed once and only its summary is kept, so the peak is about 25MB whatever the memory limit.
     */
    public function testGeneratesTheContractOfAMonolithInUnderOneSecond(): void
    {
        if (function_exists('xdebug_info') && xdebug_info('mode') !== []) {
            self::markTestSkipped('Xdebug slows every call about three times: run it with -d xdebug.mode=off, as CI runs without Xdebug');
        }
        $this->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "src/"}}}');
        for ($schema = 0; $schema < 400; $schema++) {
            $this->write(sprintf('src/Dto/Dto%d.php', $schema), self::schemaToCode($schema));
        }
        for ($controller = 0; $controller < 100; $controller++) {
            $this->write(sprintf('src/Controller/Controller%d.php', $controller), self::controllerToCode($controller));
        }
        for ($service = 0; $service < 2000; $service++) {
            $this->write(sprintf('src/Service/Service%d.php', $service), self::serviceToCode($service));
        }
        if (function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }
        $memoryBefore = memory_get_usage();
        $start = microtime(true);

        $result = GenerateCommand::runCommand(['generate'], $this->root);

        $seconds = microtime(true) - $start;
        $megabytes = (memory_get_peak_usage() - $memoryBefore) / 1024 ** 2;
        self::assertEquals(new CommandResult(0, ''), $result);
        self::assertSame(300, substr_count((string) file_get_contents($this->root . '/contracts/generated.yaml'), "      post:\n"));
        self::assertLessThan(1.0, $seconds, sprintf('generate took %.2fs', $seconds));
        self::assertLessThan(40, $megabytes, sprintf('generate took %.0fMB', $megabytes));
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

    private static function schemaToCode(int $schema): string
    {
        $types = ['string', 'int', '?string', 'bool', 'float'];
        $fields = '';
        for ($field = 0; $field < 10; $field++) {
            $fields .= sprintf("        #[BidirektField(description: 'Field %d of the resource.')]\n        public %s \$field%d,\n", $field, $types[$field % 5], $field);
        }

        return "<?php\n\nnamespace App\\Dto;\n\nuse Bidirekt\\PhpSdk\\BidirektField;\nuse Bidirekt\\PhpSdk\\BidirektSchema;\n\n"
            . sprintf("#[BidirektSchema]\nfinal class Dto%d\n{\n    public function __construct(\n%s    ) {}\n}\n", $schema, $fields);
    }

    private static function controllerToCode(int $controller): string
    {
        $actions = '';
        for ($action = 0; $action < 3; $action++) {
            $endpoint = $controller * 3 + $action;
            $actions .= sprintf(
                "    #[BidirektProvider(method: 'POST', path: '/r%d/*', request: Dto\\Dto%d::class, responses: [201 => Dto\\Dto%d::class, 404 => null, 422 => null])]\n",
                $endpoint,
                ($endpoint + 1) % 400,
                $endpoint % 400,
            );
            $actions .= sprintf("    public function action%d(): array\n    {\n        return array_map(fn (\$id) => ['id' => \$id], range(1, 10));\n    }\n\n", $action);
        }

        return sprintf("<?php\n\nnamespace App\\Controller;\n\nuse App\\Dto;\nuse Bidirekt\\PhpSdk\\BidirektProvider;\n\nfinal class Controller%d\n{\n%s}\n", $controller, $actions);
    }

    private static function serviceToCode(int $service): string
    {
        $methods = '';
        for ($method = 0; $method < 15; $method++) {
            $methods .= sprintf(
                "    public function method%d(string \$key, int \$limit = 10): ?array\n    {\n        if (isset(\$this->cache[\$key])) {\n            return \$this->cache[\$key];\n        }\n        \$this->cache[\$key] = array_slice(array_map(fn (\$x) => \$x * 2, range(1, \$limit)), 0, 5);\n\n        return \$this->cache[\$key];\n    }\n\n",
                $method,
            );
        }

        return sprintf("<?php\n\nnamespace App\\Service;\n\nfinal class Service%d\n{\n    private array \$cache = [];\n\n%s}\n", $service, $methods);
    }
}
