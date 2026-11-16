<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use Bidirekt\PhpSdk\Command\GenerateCommand;
use PHPUnit\Framework\TestCase;

/**
 * Every example between "## Quickstart" and "## The `generate` command" is a whole project: each file is a
 * fenced block right below a line that holds only its path in backticks, and the project's result is the
 * ```yaml (contracts/generated.yaml) and the ```text (stderr) blocks that follow its files. Files repeated
 * under one heading are variants of the same project (attributes and tags) with the same result.
 */
final class ReadmeTest extends TestCase
{
    private const README = __DIR__ . '/../README.md';

    private const FIRST_EXAMPLES = "\n## Quickstart\n";

    private const AFTER_EXAMPLES = "\n## The `generate` command\n";

    private const SYNTAX_ERROR = ' syntax error: ';

    private string $home;

    private string $workspace;

    private string $homeToRestore = 'HOME';

    /**
     * The workspace's parent folder is the home folder for the test, so it never reads a composer.json above it.
     */
    protected function setUp(): void
    {
        $home = sys_get_temp_dir() . '/php-sdk-readme-' . bin2hex(random_bytes(6));
        mkdir($home . '/project', 0777, true);
        $this->home = (string) realpath($home);
        $this->workspace = $this->home . '/project';
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

    /**
     * @return array<string, array{array<string, string>, ?string, ?string}> name => [files by path, yaml, stderr]
     */
    public function examples(): array
    {
        $examples = [];
        foreach (self::readmeToGroups((string) file_get_contents(self::README)) as $group) {
            $examples += self::groupToExamples($group);
        }

        return $examples;
    }

    public function testFindsTheExamples(): void
    {
        self::assertNotSame([], $this->examples());
    }

    /**
     * @dataProvider examples
     *
     * @param array<string, string> $files
     */
    public function testGeneratesWhatTheReadmeShows(array $files, ?string $yaml, ?string $stderr): void
    {
        self::writeFiles($this->workspace, $files);

        $result = GenerateCommand::runCommand(['generate'], $this->workspace);

        $generated = $this->workspace . '/contracts/generated.yaml';
        self::assertSame($stderr ?? '', $result->stderr, 'stderr');
        if ($yaml === null) {
            self::assertSame(1, $result->exitCode, 'exit code');
            self::assertFileDoesNotExist($generated, 'nothing is written');

            return;
        }
        self::assertSame(0, $result->exitCode, 'exit code');
        self::assertSame($yaml, file_get_contents($generated), 'output');
    }

    /**
     * @dataProvider examples
     *
     * @param array<string, string> $files
     */
    public function testExampleCompiles(array $files, ?string $yaml, ?string $stderr): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('the examples use Enum::Case->value in attributes, which PHP compiles from 8.2');
        }
        self::writeFiles($this->workspace, $files);
        $sources = array_filter(array_keys($files), static fn(string $path): bool => str_ends_with($path, '.php'));
        $showsSyntaxError = str_contains($stderr ?? '', self::SYNTAX_ERROR);

        $failures = [];
        foreach ($sources as $path) {
            $output = [];
            exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($this->workspace . '/' . $path)), $output, $exitCode);
            if ($exitCode !== 0) {
                $failures[] = $path;
            }
        }

        if ($showsSyntaxError) {
            self::assertNotSame([], $failures, 'php -l rejects the syntax error');

            return;
        }
        self::assertSame([], $failures, 'php -l rejects these files');
    }

    /**
     * @return list<array{code: list<array{heading: string, path: string, body: string}>, outputs: list<array{lang: string, line: int, body: string}>}>
     */
    private static function readmeToGroups(string $readme): array
    {
        $start = strpos($readme, self::FIRST_EXAMPLES);
        $end = strpos($readme, self::AFTER_EXAMPLES);
        self::assertTrue($start !== false && $end !== false && $end > $start, 'the README has its example sections');
        $startLine = substr_count(substr($readme, 0, $start), "\n") + 2;
        $lines = explode("\n", substr($readme, $start + 1, $end - $start));
        $groups = [];
        $current = ['code' => [], 'outputs' => []];
        $heading = '';
        $index = 0;
        $count = count($lines);
        while ($index < $count) {
            $line = $lines[$index];
            if (preg_match('/^(`{3,})(\w*)$/', $line, $fence) !== 1) {
                if (preg_match('/^#{2,4} (.+)$/', $line, $title) === 1) {
                    $heading = $title[1];
                }
                ++$index;

                continue;
            }
            $readmeLine = $startLine + $index;
            $closing = array_search($fence[1], array_slice($lines, $index + 1, null, true), true);
            self::assertIsInt($closing, sprintf('the block at README line %d is closed', $readmeLine));
            $body = implode("\n", array_slice($lines, $index + 1, $closing - $index - 1)) . "\n";
            $path = null;
            if ($index > 0 && preg_match('/^`([^`\s]+)`$/', $lines[$index - 1], $named) === 1) {
                $path = $named[1];
            }
            $lang = $fence[2];
            if ($path !== null) {
                if ($current['outputs'] !== []) {
                    $groups[] = $current;
                    $current = ['code' => [], 'outputs' => []];
                }
                $current['code'][] = ['heading' => $heading, 'path' => $path, 'body' => $body];
            }
            if ($path === null && $lang === 'php') {
                self::fail(sprintf('the php block at README line %d is below a line with its path, as `src/Pet.php`', $readmeLine));
            }
            if ($path === null && ($lang === 'yaml' || $lang === 'text')) {
                self::assertNotSame([], $current['code'], sprintf('the %s block at README line %d follows an example', $lang, $readmeLine));
                $current['outputs'][] = ['lang' => $lang, 'line' => $readmeLine, 'body' => $body];
            }
            $index = $closing + 1;
        }
        if ($current['code'] !== []) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param array{code: list<array{heading: string, path: string, body: string}>, outputs: list<array{lang: string, line: int, body: string}>} $group
     *
     * @return array<string, array{array<string, string>, ?string, ?string}>
     */
    private static function groupToExamples(array $group): array
    {
        self::assertNotSame([], $group['outputs'], sprintf('the example under "%s" has an output', $group['code'][0]['heading']));
        $location = sprintf('README line %d', $group['outputs'][0]['line']);
        $yamls = [];
        $texts = [];
        foreach ($group['outputs'] as $output) {
            if ($output['lang'] === 'yaml') {
                $yamls[] = $output['body'];

                continue;
            }
            $texts[] = $output['body'];
        }
        self::assertTrue(count($yamls) <= 1 && count($texts) <= 1, sprintf('one yaml and one text at most at %s', $location));
        $byHeading = [];
        foreach ($group['code'] as $block) {
            $byHeading[$block['heading']][$block['path']][] = $block['body'];
        }
        $examples = [];
        foreach ($byHeading as $heading => $byPath) {
            $counts = array_unique(array_filter(array_map('count', $byPath), static fn(int $count): bool => $count > 1));
            self::assertLessThanOrEqual(1, count($counts), sprintf('files repeat as many times each under "%s"', $heading));
            $variants = max([1, ...array_values($counts)]);
            for ($variant = 0; $variant < $variants; ++$variant) {
                $files = [];
                foreach ($byPath as $path => $bodies) {
                    $files[$path] = $bodies[min($variant, count($bodies) - 1)];
                }
                $name = sprintf('%s (%s) #%d, %s', $heading, implode(', ', array_keys($byPath)), $variant + 1, $location);
                $examples[$name] = [$files, $yamls[0] ?? null, $texts[0] ?? null];
            }
        }

        return $examples;
    }

    /**
     * @param array<string, string> $files
     */
    private static function writeFiles(string $directory, array $files): void
    {
        foreach ($files as $path => $body) {
            $file = $directory . '/' . $path;
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }
            file_put_contents($file, $body);
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
