<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\DirectoryToProject;
use Bidirekt\PhpSdk\Reader\Project;
use Bidirekt\PhpSdk\Reader\ProjectToFiles;
use PHPUnit\Framework\TestCase;

final class ProjectToFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-project-to-files-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
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

    public function testReadsThePhpFilesOutsideTheVendorAutoloadDevNodeModulesAndHiddenFolders(): void
    {
        $this->write('composer.json', '{"autoload": {"psr-4": {"App\\\\": "src/"}}, "autoload-dev": {"psr-4": {"App\\\\Tests\\\\": "tests/"}, "files": ["helpers/dev.php"]}}');
        $this->write('src/Pet.php', '<?php');
        $this->write('src/Owner.php', '<?php');
        $this->write('src/readme.md', '');
        $this->write('helpers/dev.php', '<?php');
        $this->write('helpers/app.php', '<?php');
        $this->write('index.php', '<?php');
        $this->write('tests/PetTest.php', '<?php');
        $this->write('vendor/acme/base/Base.php', '<?php');
        $this->write('node_modules/tool/tool.php', '<?php');
        $this->write('.cache/cached.php', '<?php');
        $this->write('src/.generated/Stub.php', '<?php');

        self::assertSame(['helpers/app.php', 'index.php', 'src/Owner.php', 'src/Pet.php'], $this->filesOf($this->root));
    }

    public function testSkipsTheConfiguredVendorDirInsteadOfReadingIt(): void
    {
        $this->write('composer.json', '{"config": {"vendor-dir": "deps/libs"}}');
        $this->write('deps/libs/acme/Base.php', '<?php');
        $this->write('deps/own.php', '<?php');
        $this->write('src/Pet.php', '<?php');

        self::assertSame(['deps/own.php', 'src/Pet.php'], $this->filesOf($this->root));
    }

    public function testReadsEveryPhpFileOutsideTheExcludedFoldersWithoutComposerJson(): void
    {
        $this->write('src/Pet.php', '<?php');
        $this->write('tests/PetTest.php', '<?php');
        $this->write('vendor/acme/Base.php', '<?php');
        $this->write('node_modules/tool/tool.php', '<?php');
        $this->write('.git/hook.php', '<?php');

        self::assertSame(['src/Pet.php', 'tests/PetTest.php'], $this->filesOf($this->root));
    }

    public function testExcludesTheAutoloadDevOfTheComposerJsonAboveASubfolderProject(): void
    {
        $this->write('composer.json', '{"autoload-dev": {"psr-4": {"Api\\\\Tests\\\\": "apps/api/tests/"}}}');
        $this->write('apps/api/src/Pet.php', '<?php');
        $this->write('apps/api/tests/PetTest.php', '<?php');
        $this->write('apps/web/Page.php', '<?php');

        self::assertSame(['src/Pet.php'], $this->filesOf($this->root . '/apps/api'));
    }

    public function testReadsAPathRepositoryPackageOutsideTheVendorFolderAsProjectCode(): void
    {
        $this->write('packages/pets/composer.json', '{"name": "acme/pets", "autoload-dev": {"psr-4": {"Pets\\\\Tests\\\\": "tests/"}}}');
        $this->write('packages/pets/src/Pet.php', '<?php');
        $this->write('packages/pets/tests/PetTest.php', '<?php');
        $this->write('packages/dev-tool/src/Tool.php', '<?php');
        $this->write('app/composer.json', '{}');
        $this->write('app/src/Controller.php', '<?php');
        $this->write('app/vendor/acme/installed/Installed.php', '<?php');
        $this->write('app/vendor/composer/installed.json', (string) json_encode([
            'packages' => [
                ['name' => 'acme/pets', 'install-path' => '../acme/pets'],
                ['name' => 'acme/dev-tool', 'install-path' => '../acme/dev-tool'],
                ['name' => 'acme/installed', 'install-path' => '../acme/installed'],
            ],
            'dev' => true,
            'dev-package-names' => ['acme/dev-tool'],
        ]));
        symlink('../../../packages/pets', $this->root . '/app/vendor/acme/pets');
        symlink('../../../packages/dev-tool', $this->root . '/app/vendor/acme/dev-tool');

        self::assertSame(['src/Controller.php', '../packages/pets/src/Pet.php'], $this->filesOf($this->root . '/app'));
    }

    public function testReadsAPathPackageInsideTheProjectOnceWithItsOwnExclusions(): void
    {
        $this->write('composer.json', '{}');
        $this->write('packages/pets/composer.json', '{"autoload-dev": {"psr-4": {"Pets\\\\Tests\\\\": "tests/"}}}');
        $this->write('packages/pets/src/Pet.php', '<?php');
        $this->write('packages/pets/tests/PetTest.php', '<?php');
        $this->write('vendor/composer/installed.json', (string) json_encode([
            'packages' => [['name' => 'acme/pets', 'install-path' => '../acme/pets']],
            'dev-package-names' => [],
        ]));
        mkdir($this->root . '/vendor/acme');
        symlink('../../packages/pets', $this->root . '/vendor/acme/pets');

        self::assertSame(['packages/pets/src/Pet.php'], $this->filesOf($this->root));
    }

    public function testReadsASymlinkLoopOnce(): void
    {
        $this->write('src/Pet.php', '<?php');
        symlink('..', $this->root . '/src/loop');

        self::assertSame(['src/Pet.php'], $this->filesOf($this->root));
    }

    public function testWarnsOfAFolderItCannotReadAndSkipsIt(): void
    {
        $this->write('src/Pet.php', '<?php');
        $this->write('locked/Owner.php', '<?php');
        chmod($this->root . '/locked', 0);
        if (is_readable($this->root . '/locked')) {
            chmod($this->root . '/locked', 0755);
            self::markTestSkipped('the folder stays readable: run the tests as a user other than root');
        }
        $project = DirectoryToProject::directoryToProject($this->root);
        self::assertInstanceOf(Project::class, $project);

        $files = ProjectToFiles::projectToFiles($project);
        chmod($this->root . '/locked', 0755);

        self::assertSame([$this->root . '/src/Pet.php'], $files->paths);
        self::assertEquals([
            new Diagnostic('cannot read the folder, so its files are not read: check that it can be read', new SourceLocation($this->root . '/locked')),
        ], $files->warnings);
    }

    /**
     * @return list<string>
     */
    private function filesOf(string $directory): array
    {
        $project = DirectoryToProject::directoryToProject($directory);
        self::assertInstanceOf(Project::class, $project);
        $relative = [];
        foreach (ProjectToFiles::projectToFiles($project)->paths as $file) {
            $relative[] = $this->relativeTo($project->directory, $file);
        }

        return $relative;
    }

    private function relativeTo(string $directory, string $file): string
    {
        if (str_starts_with($file, $directory . '/')) {
            return substr($file, strlen($directory) + 1);
        }

        return '../' . $this->relativeTo(dirname($directory), $file);
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
