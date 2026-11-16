<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\NameScope;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class NameScopeTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/php-sdk-name-scope-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testResolvesADocblockNameThroughAnAliasedUse(): void
    {
        $scope = $this->scopeOfFirstProperty(<<<'PHP'
            <?php

            namespace App\Http;

            use App\Pet as P;
            use App\Models\{Owner, Tag as Label};
            use function App\helper;

            final class Shelter
            {
                /** @var list<P> */
                public array $pets;
            }
            PHP);

        self::assertSame('App\\Pet', $scope->classNameToFullyQualified('P'));
        self::assertSame('App\\Pet', $scope->classNameToFullyQualified('p'));
        self::assertSame('App\\Pet\\Photo', $scope->classNameToFullyQualified('P\\Photo'));
        self::assertSame('App\\Models\\Owner', $scope->classNameToFullyQualified('Owner'));
        self::assertSame('App\\Models\\Tag', $scope->classNameToFullyQualified('Label'));
        self::assertSame('App\\Http\\helper', $scope->classNameToFullyQualified('helper'));
    }

    public function testResolvesAnUnimportedNameThroughTheNamespace(): void
    {
        $scope = $this->scopeOfFirstProperty(<<<'PHP'
            <?php

            namespace App\Http;

            final class Shelter
            {
                /** @var list<Pet> */
                public array $pets;
            }
            PHP);

        self::assertSame('App\\Http', $scope->namespace);
        self::assertSame('App\\Http\\Pet', $scope->classNameToFullyQualified('Pet'));
        self::assertSame('App\\Http\\Models\\Pet', $scope->classNameToFullyQualified('Models\\Pet'));
        self::assertSame('Acme\\Pet', $scope->classNameToFullyQualified('\\Acme\\Pet'));
        self::assertSame('App\\Http\\Pet', $scope->classNameToFullyQualified('namespace\\Pet'));
        self::assertSame('self', $scope->classNameToFullyQualified('self'));
        self::assertFalse($scope->hasAlias('Pet'));
    }

    public function testResolvesInTheGlobalNamespaceWithoutPrefix(): void
    {
        $scope = $this->scopeOfFirstProperty(<<<'PHP'
            <?php

            final class Shelter
            {
                /** @var list<Pet> */
                public array $pets;
            }
            PHP);

        self::assertSame('Pet', $scope->classNameToFullyQualified('Pet'));
    }

    public function testKeepsTheScopeOfEachNamespaceInAFile(): void
    {
        $parsed = $this->parse(<<<'PHP'
            <?php

            namespace App\Models {
                use Acme\Pet;

                final class Shelter
                {
                    /** @var list<Pet> */
                    public array $pets;
                }
            }

            namespace App\Http {
                final class Kennel
                {
                    /** @var list<Pet> */
                    public array $pets;
                }
            }
            PHP);
        $properties = (new NodeFinder())->findInstanceOf($parsed->statements, Property::class);

        self::assertSame('Acme\\Pet', NameScope::ofNode($properties[0])->classNameToFullyQualified('Pet'));
        self::assertSame('App\\Http\\Pet', NameScope::ofNode($properties[1])->classNameToFullyQualified('Pet'));
    }

    private function scopeOfFirstProperty(string $code): NameScope
    {
        $property = (new NodeFinder())->findFirstInstanceOf($this->parse($code)->statements, Property::class);
        self::assertNotNull($property);

        return NameScope::ofNode($property);
    }

    private function parse(string $code): ParsedFile
    {
        file_put_contents($this->file, $code);
        $parsed = (new PhpFileToParsed())->fileToParsed($this->file);
        self::assertInstanceOf(ParsedFile::class, $parsed);

        return $parsed;
    }
}
