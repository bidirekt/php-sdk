<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Reader\Composer;
use Bidirekt\PhpSdk\Reader\DirectoryToProject;
use Bidirekt\PhpSdk\Reader\Project;
use PHPUnit\Framework\TestCase;

final class DirectoryToProjectTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-directory-to-project-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
    }

    public function testTakesTheVendorAndAutoloadDevFromTheComposerJsonInTheFolder(): void
    {
        $this->write('composer.json', '{"autoload-dev": {"psr-4": {"App\\\\Tests\\\\": ["tests/", "spec"]}, "psr-0": {"Old_": "old/"}, "classmap": ["fixtures/"], "files": ["./dev/helpers.php"]}}');

        $project = DirectoryToProject::directoryToProject($this->root . '/');

        self::assertEquals(new Project($this->root, new Composer($this->root, $this->root . '/vendor', [
            $this->root . '/tests',
            $this->root . '/spec',
            $this->root . '/old',
            $this->root . '/fixtures',
            $this->root . '/dev/helpers.php',
        ])), $project);
    }

    public function testTakesTheConfiguredVendorDir(): void
    {
        $this->write('composer.json', '{"config": {"vendor-dir": "deps/../libs"}}');

        $project = DirectoryToProject::directoryToProject($this->root);

        self::assertInstanceOf(Project::class, $project);
        self::assertNotNull($project->composer);
        self::assertSame($this->root . '/libs', $project->composer->vendorDirectory);
    }

    public function testFindsTheNearestComposerJsonAboveTheFolder(): void
    {
        $this->write('composer.json', '{}');
        mkdir($this->root . '/apps/api', 0777, true);

        $project = DirectoryToProject::directoryToProject($this->root . '/apps/api');

        self::assertEquals(new Project($this->root . '/apps/api', new Composer($this->root, $this->root . '/vendor', [])), $project);
    }

    public function testHasNoComposerContextWithoutComposerJson(): void
    {
        $project = DirectoryToProject::directoryToProject($this->root);

        self::assertInstanceOf(Project::class, $project);
        self::assertSame($this->root, $project->directory);
        self::assertNull($project->composer);
    }

    public function testRejectsAFolderThatDoesNotExist(): void
    {
        self::assertEquals(
            new Diagnostic(sprintf('cannot read the project folder "%s/missing": pass an existing folder', $this->root)),
            DirectoryToProject::directoryToProject($this->root . '/missing'),
        );
    }

    public function testRejectsAComposerJsonThatIsNotJson(): void
    {
        $this->write('composer.json', '{');

        $diagnostic = DirectoryToProject::directoryToProject($this->root);

        self::assertInstanceOf(Diagnostic::class, $diagnostic);
        self::assertSame('cannot read the file as JSON: fix composer.json', $diagnostic->message);
        self::assertNotNull($diagnostic->location);
        self::assertSame($this->root . '/composer.json', $diagnostic->location->file);
        self::assertSame(1, $diagnostic->location->line);
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
