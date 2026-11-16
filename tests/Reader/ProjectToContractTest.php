<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\DirectoryToProject;
use Bidirekt\PhpSdk\Reader\Project;
use Bidirekt\PhpSdk\Reader\ProjectToContract;
use Bidirekt\PhpSdk\Reader\ReadResult;
use PHPUnit\Framework\TestCase;

final class ProjectToContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-project-to-contract-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->root);
    }

    public function testReturnsOnlyTheSyntaxErrorsOfEveryFile(): void
    {
        $this->write('owner.php', "<?php\n\nfinal class Owner\n{\n    #[BidirektField]\n    public string \$name;\n");
        $this->write('pet.php', "<?php\n\nfinal class Pet\n{\n    public string \$broken =;\n}\n");

        self::assertEquals(new ReadResult(new Contract(), [
            new Diagnostic('syntax error: unexpected EOF: fix the code so PHP can parse it', new SourceLocation($this->root . '/owner.php', 7)),
            new Diagnostic("syntax error: unexpected ';': fix the code so PHP can parse it", new SourceLocation($this->root . '/pet.php', 5)),
        ], []), $this->read());
    }

    public function testWarnsOfAnSdkAttributeWithoutUseInTheProjectFilesOnly(): void
    {
        $this->write('composer.json', '{"autoload-dev": {"psr-4": {"App\\\\Tests\\\\": "tests/"}}}');
        $this->write('src/Pet.php', "<?php\nnamespace App;\n\nfinal class Pet\n{\n    #[BidirektField]\n    public int \$petId;\n}\n");
        $this->write('tests/PetTest.php', "<?php\nnamespace App\\Tests;\n\nfinal class PetTest\n{\n    #[BidirektField]\n    public int \$petId;\n}\n");

        self::assertEquals(new ReadResult(new Contract(), [], [
            new Diagnostic('#[BidirektField] resolves to "App\\BidirektField", so it is not read: write use Bidirekt\\PhpSdk\\BidirektField', new SourceLocation($this->root . '/src/Pet.php', 6)),
        ]), $this->read());
    }

    private function read(): ReadResult
    {
        $project = DirectoryToProject::directoryToProject($this->root);
        self::assertInstanceOf(Project::class, $project);

        return ProjectToContract::projectToContract($project);
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
