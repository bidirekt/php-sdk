<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use Bidirekt\PhpSdk\Command\GenerateCommand;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    private string $workspace;

    private string $homeToRestore = 'HOME';

    /**
     * The workspace is the home folder for the run, so a case never reads a composer.json above it.
     */
    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir() . '/php-sdk-fixtures-' . bin2hex(random_bytes(6));
        mkdir($this->workspace);
        $home = getenv('HOME');
        if (is_string($home)) {
            $this->homeToRestore = 'HOME=' . $home;
        }
        putenv('HOME=' . $this->workspace);
    }

    protected function tearDown(): void
    {
        putenv($this->homeToRestore);
        self::removeDirectory($this->workspace);
    }

    /**
     * @return array<string, array{string}>
     */
    public function cases(): array
    {
        $cases = [];
        foreach ((array) scandir(self::FIXTURES) as $name) {
            if (is_string($name) && $name !== '.' && $name !== '..' && is_dir(self::FIXTURES . '/' . $name)) {
                $cases[$name] = [$name];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider cases
     */
    public function testGeneratesTheExpectedResult(string $name): void
    {
        $caseDirectory = self::FIXTURES . '/' . $name;
        $expectedYaml = self::readIfExists($caseDirectory . '/expected.yaml');
        $expectedError = self::readIfExists($caseDirectory . '/expected-error.txt');
        $expectedStderr = self::readIfExists($caseDirectory . '/expected-stderr.txt');
        self::assertTrue(($expectedYaml === null) !== ($expectedError === null), 'a case has exactly one of expected.yaml and expected-error.txt');
        self::assertTrue($expectedError === null || $expectedStderr === null, 'expected-stderr.txt only goes with expected.yaml');
        $cwd = $this->workspace . '/' . $name;
        self::copyDirectory($caseDirectory, $cwd);
        $before = self::filesUnder($cwd);

        $result = GenerateCommand::runCommand(['generate', ...self::caseArguments($cwd)], $cwd);

        if ($expectedError !== null) {
            self::assertSame($expectedError, $result->stderr, 'stderr');
            self::assertSame(1, $result->exitCode, 'exit code');
            self::assertSame($before, self::filesUnder($cwd), 'nothing is written');

            return;
        }
        self::assertSame($expectedStderr ?? '', $result->stderr, 'stderr');
        self::assertSame(0, $result->exitCode, 'exit code');
        self::assertSame($expectedYaml, self::readIfExists($cwd . '/contracts/generated.yaml'), 'output');
    }

    /**
     * @return list<string>
     */
    private static function caseArguments(string $cwd): array
    {
        $args = preg_split('/\s+/', self::readIfExists($cwd . '/args.txt') ?? '', -1, PREG_SPLIT_NO_EMPTY);
        if ($args === false) {
            return [];
        }

        return $args;
    }

    private static function readIfExists(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        return (string) file_get_contents($path);
    }

    /**
     * @return array<string, string> relative path => content, symlinks as their target
     */
    private static function filesUnder(string $directory, string $prefix = ''): array
    {
        $files = [];
        foreach (array_diff((array) scandir($directory), ['.', '..']) as $entry) {
            $path = $directory . '/' . $entry;
            $relative = $prefix . $entry;
            if (is_link($path)) {
                $files[$relative] = 'link to ' . readlink($path);

                continue;
            }
            if (is_dir($path)) {
                $files += self::filesUnder($path, $relative . '/');

                continue;
            }
            $files[$relative] = (string) file_get_contents($path);
        }

        return $files;
    }

    private static function copyDirectory(string $from, string $to): void
    {
        mkdir($to);
        foreach (array_diff((array) scandir($from), ['.', '..']) as $entry) {
            $source = $from . '/' . $entry;
            if (is_link($source)) {
                symlink((string) readlink($source), $to . '/' . $entry);

                continue;
            }
            if (is_dir($source)) {
                self::copyDirectory($source, $to . '/' . $entry);

                continue;
            }
            copy($source, $to . '/' . $entry);
        }
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
}
