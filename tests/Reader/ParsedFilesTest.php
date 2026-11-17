<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use PHPUnit\Framework\TestCase;

final class ParsedFilesTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/php-sdk-parsed-files-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
        file_put_contents($this->directory . '/first.php', "<?php\n\nfinal class Pet {}\n");
        // About 15,000 tokens: more than a third of 16M at 600 bytes per token, less than a third of 1536M.
        file_put_contents($this->directory . '/numbers.php', '<?php $numbers = [' . str_repeat('1, ', 5000) . "];\n");
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/first.php');
        unlink($this->directory . '/numbers.php');
        rmdir($this->directory);
    }

    public function testKeepsFilesUpToTheMemoryLimitPhpStartedWithNotTheRaisedOne(): void
    {
        self::assertSame('false', $this->keepsTheSecondFileAfterRaisingTheLimit('16M'));
        self::assertSame('true', $this->keepsTheSecondFileAfterRaisingTheLimit('1536M'));
    }

    private function keepsTheSecondFileAfterRaisingTheLimit(string $startingLimit): string
    {
        $script = sprintf(
            <<<'PHP'
                require %s;
                ini_set('memory_limit', '1536M');
                $files = new Bidirekt\PhpSdk\Reader\ParsedFiles();
                $files->pathToParsed(%s);
                var_export($files->pathToParsed(%s) === $files->pathToParsed(%3$s));
                PHP,
            var_export(__DIR__ . '/../../vendor/autoload.php', true),
            var_export($this->directory . '/first.php', true),
            var_export($this->directory . '/numbers.php', true),
        );
        $process = proc_open(
            [PHP_BINARY, '-d', 'memory_limit=' . $startingLimit, '-r', $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        self::assertSame(0, proc_close($process), $errors);

        return $output;
    }
}
