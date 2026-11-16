<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\ClassIndex;
use Bidirekt\PhpSdk\Reader\DirectoryToProject;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\ParsedFiles;
use Bidirekt\PhpSdk\Reader\Project;
use Bidirekt\PhpSdk\Reader\ProjectToFiles;
use PHPUnit\Framework\TestCase;

final class ClassIndexTest extends TestCase
{
    private const VENDOR_HEADER = "<?php\n\$vendorDir = dirname(__DIR__);\n\$baseDir = dirname(\$vendorDir);\n\n";

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-class-index-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
    }

    public function testFindsEveryClassLikeOfTheProjectByItsCaseInsensitiveName(): void
    {
        $this->write('src/Pet.php', "<?php\nnamespace App;\n\nfinal class Pet {}\ninterface Named {}\ntrait HasName {}\nenum Status: string { case Sold = 'sold'; }\n");
        $classes = $this->index();
        $usedAt = new SourceLocation($this->root . '/src/Shelter.php', 9);

        foreach (['App\\Pet', '\\app\\pet', 'App\\Named', 'App\\HasName', 'App\\Status'] as $class) {
            $declaration = $classes->lookup($class, $usedAt)->declaration;
            self::assertNotNull($declaration, $class);
            self::assertSame($this->root . '/src/Pet.php', $declaration->file->path);
        }
        self::assertSame('App\\Status', $classes->lookup('app\\status', $usedAt)->declaration?->name);
    }

    public function testErrorsOnAClassDeclaredInTwoFilesOnlyWhenItIsLookedUp(): void
    {
        $this->write('_ide_helper_models.php', "<?php\nnamespace App {\n    /** helper */\n    class Pet {}\n}\n");
        $this->write('src/Pet.php', "<?php\nnamespace App;\n\n#[\\Bidirekt\\PhpSdk\\BidirektSchema]\nfinal class Pet {}\n");
        $this->write('src/Owner.php', "<?php\nnamespace App;\n\nfinal class Owner {}\n");
        $classes = $this->index();
        $usedAt = new SourceLocation($this->root . '/src/Owner.php', 4);

        self::assertNotNull($classes->lookup('App\\Owner', $usedAt)->declaration);
        $lookup = $classes->lookup('App\\Pet', $usedAt);
        self::assertNull($lookup->declaration);
        self::assertEquals([
            new Diagnostic('class "App\\Pet" is declared in more than one file generate reads: keep a single declaration', new SourceLocation($this->root . '/_ide_helper_models.php', 4)),
            new Diagnostic('class "App\\Pet" is declared in more than one file generate reads: keep a single declaration', new SourceLocation($this->root . '/src/Pet.php', 5)),
        ], $lookup->errors);
        self::assertSame($lookup->errors, $classes->lookup('App\\Pet', $usedAt)->errors);
    }

    public function testReadsAVendorClassOnDemandThroughTheAutoloadMaps(): void
    {
        $this->write('composer.json', '{}');
        $this->write('src/Dog.php', "<?php\nnamespace App;\n\nfinal class Dog extends \\Acme\\Animal {}\n");
        $this->write('vendor/composer/autoload_psr4.php', self::VENDOR_HEADER . "return array('Acme\\\\' => array(\$vendorDir . '/acme/zoo/src'));\n");
        $this->write('vendor/acme/zoo/src/Animal.php', "<?php\nnamespace Acme;\n\nabstract class Animal {}\nclass Bird {}\n");
        $this->write('vendor/acme/zoo/src/Broken.php', "<?php\nnamespace Acme;\n\nclass Broken {\n");
        $classes = $this->index();
        $usedAt = new SourceLocation($this->root . '/src/Dog.php', 4);

        $animal = $classes->lookup('Acme\\Animal', $usedAt)->declaration;
        self::assertNotNull($animal);
        self::assertSame($this->root . '/vendor/acme/zoo/src/Animal.php', $animal->file->path);
        self::assertNull($classes->lookup('Acme\\Missing', $usedAt)->declaration);
        self::assertSame([], $classes->lookup('Acme\\Missing', $usedAt)->errors);
        self::assertEquals(
            [new Diagnostic("syntax error: unexpected EOF: fix the code so PHP can parse it", new SourceLocation($this->root . '/vendor/acme/zoo/src/Broken.php', 5))],
            $classes->lookup('Acme\\Broken', $usedAt)->errors,
        );
    }

    public function testAsksForComposerInstallWhenTheVendorFolderIsMissing(): void
    {
        $this->write('composer.json', '{}');
        $this->write('src/Dog.php', "<?php\nnamespace App;\n\nfinal class Dog extends \\Acme\\Animal {}\n");
        $usedAt = new SourceLocation($this->root . '/src/Dog.php', 4);

        $lookup = $this->index()->lookup('Acme\\Animal', $usedAt);

        self::assertNull($lookup->declaration);
        self::assertEquals(
            [new Diagnostic('cannot find class "Acme\\Animal" in the project, and the vendor folder is not installed: run composer install', $usedAt)],
            $lookup->errors,
        );
    }

    public function testFindsNothingOutsideTheProjectWithoutComposerJson(): void
    {
        $this->write('src/Dog.php', "<?php\nnamespace App;\n\nfinal class Dog extends \\Acme\\Animal {}\n");

        $lookup = $this->index()->lookup('Acme\\Animal', new SourceLocation($this->root . '/src/Dog.php', 4));

        self::assertNull($lookup->declaration);
        self::assertSame([], $lookup->errors);
    }

    public function testRejectsAnAutoloadMapItCannotReadOnlyWhenAVendorClassIsLookedUp(): void
    {
        $this->write('composer.json', '{}');
        $this->write('src/Pet.php', "<?php\nnamespace App;\n\nfinal class Pet {}\n");
        $this->write('vendor/composer/autoload_classmap.php', "<?php\nreturn 'Acme\\\\Animal';\n");
        $classes = $this->index();
        $usedAt = new SourceLocation($this->root . '/src/Pet.php', 4);

        self::assertNotNull($classes->lookup('App\\Pet', $usedAt)->declaration);
        self::assertEquals(
            [new Diagnostic('cannot read the Composer autoload map: run composer install again', new SourceLocation($this->root . '/vendor/composer/autoload_classmap.php', 1))],
            $classes->lookup('Acme\\Animal', $usedAt)->errors,
        );
    }

    private function index(): ClassIndex
    {
        $project = DirectoryToProject::directoryToProject($this->root);
        self::assertInstanceOf(Project::class, $project);
        $files = new ParsedFiles();
        $index = new ClassIndex($files, $project->composer?->vendorDirectory);
        foreach (ProjectToFiles::projectToFiles($project)->paths as $path) {
            $read = $files->projectFileToRead($path);
            self::assertNotInstanceOf(Diagnostic::class, $read);
            if ($read instanceof ParsedFile) {
                $index->addParsedFile($read);

                continue;
            }
            $index->addDeclaredClasses($read);
        }

        return $index;
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
