<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use PHPUnit\Framework\TestCase;

final class BinTest extends TestCase
{
    public function testPrintsTheUsageWithoutTheGenerateCommand(): void
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../bin/php-sdk'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);

        self::assertSame('', stream_get_contents($pipes[1]));
        self::assertSame(
            "Usage: php-sdk generate [--check] [--project <dir>] [--out <file>]\n",
            stream_get_contents($pipes[2]),
        );
        self::assertSame(1, proc_close($process));
    }

    public function testReadsAnExpressionNestedDeeperThanXdebugAllowsByDefault(): void
    {
        if (!extension_loaded('xdebug')) {
            self::markTestSkipped('Xdebug is not loaded');
        }
        $home = sys_get_temp_dir() . '/php-sdk-bin-' . bin2hex(random_bytes(6));
        $project = $home . '/project';
        mkdir($project, 0777, true);
        file_put_contents($project . '/deep.php', '<?php $sum = ' . str_repeat('(', 600) . '1' . str_repeat(' + 1)', 600) . ";\n");
        file_put_contents($project . '/Pet.php', <<<'PHP'
            <?php

            #[Bidirekt\PhpSdk\BidirektSchema]
            final class Pet
            {
                #[Bidirekt\PhpSdk\BidirektField]
                public string $name;
            }
            PHP);
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=develop', '-d', 'xdebug.max_nesting_level=512', __DIR__ . '/../bin/php-sdk', 'generate'],
            [1 => ['file', $project . '/stdout.txt', 'w'], 2 => ['file', $project . '/stderr.txt', 'w']],
            $pipes,
            $project,
            // The project's parent folder is its home folder, so it never reads a composer.json above it.
            ['HOME' => (string) realpath($home)] + getenv(),
        );
        self::assertIsResource($process);

        $exitCode = proc_close($process);
        $output = file_get_contents($project . '/stdout.txt') . file_get_contents($project . '/stderr.txt');
        $generated = @file_get_contents($project . '/contracts/generated.yaml');
        foreach (['deep.php', 'Pet.php', 'stdout.txt', 'stderr.txt', 'contracts/generated.yaml'] as $file) {
            @unlink($project . '/' . $file);
        }
        @rmdir($project . '/contracts');
        rmdir($project);
        rmdir($home);

        self::assertSame('', $output);
        self::assertSame(0, $exitCode);
        self::assertStringContainsString("  Pet:\n", (string) $generated);
    }
}
