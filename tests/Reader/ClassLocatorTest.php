<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\AutoloadMaps;
use Bidirekt\PhpSdk\Reader\ClassLocator;
use PHPUnit\Framework\TestCase;

final class ClassLocatorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-class-locator-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
    }

    public function testFindsAClassInTheClassmapFirst(): void
    {
        $this->write('classmap/Base.php');
        $this->write('psr4/Base.php');
        $locator = new ClassLocator(new AutoloadMaps(
            ['Acme\\' => [$this->root . '/psr4']],
            [],
            ['Acme\\Base' => $this->root . '/classmap/Base.php'],
        ));

        self::assertSame($this->root . '/classmap/Base.php', $locator->classToFile('\\Acme\\Base'));
    }

    public function testFindsAClassByTheLongestPsr4PrefixThenTheNextDirectories(): void
    {
        $this->write('base/src/Model/Pet.php');
        $this->write('second/Pet.php');
        $locator = new ClassLocator(new AutoloadMaps(
            [
                'Acme\\' => [$this->root . '/acme'],
                'Acme\\Base\\' => [$this->root . '/base/src'],
                'Acme\\Pets\\' => [$this->root . '/first', $this->root . '/second'],
            ],
            [],
            [],
        ));

        self::assertSame($this->root . '/base/src/Model/Pet.php', $locator->classToFile('Acme\\Base\\Model\\Pet'));
        self::assertSame($this->root . '/second/Pet.php', $locator->classToFile('Acme\\Pets\\Pet'));
        self::assertNull($locator->classToFile('Acme\\Base\\Missing'));
    }

    public function testFindsAClassByPsr0WithUnderscoresAsFolders(): void
    {
        $this->write('legacy/Old/Pet/Model.php');
        $this->write('legacy/Acme/Old/Pet_Model.php');
        $locator = new ClassLocator(new AutoloadMaps([], ['Old_' => [$this->root . '/legacy'], 'Acme\\Old\\' => [$this->root . '/legacy']], []));

        self::assertSame($this->root . '/legacy/Old/Pet/Model.php', $locator->classToFile('Old_Pet_Model'));
        self::assertNull($locator->classToFile('Acme\\Old\\Pet_Model'));
    }

    public function testFindsANamespacedPsr0ClassWithUnderscoresOnlyInTheClassName(): void
    {
        $this->write('legacy/Acme_Old/Pet/Model.php');
        $locator = new ClassLocator(new AutoloadMaps([], ['Acme_Old\\' => [$this->root . '/legacy']], []));

        self::assertSame($this->root . '/legacy/Acme_Old/Pet/Model.php', $locator->classToFile('Acme_Old\\Pet_Model'));
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

    private function write(string $path): void
    {
        $file = $this->root . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, '<?php');
    }
}
