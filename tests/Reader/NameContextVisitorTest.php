<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\NameContextVisitor;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\NameContext;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class NameContextVisitorTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/php-sdk-name-context-visitor-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testResolvesADocblockNameThroughAnAliasedUse(): void
    {
        $names = $this->namesOfFirstProperty(<<<'PHP'
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

        self::assertSame('App\\Pet', NameContextVisitor::classNameToFullyQualified($names, 'P'));
        self::assertSame('App\\Pet', NameContextVisitor::classNameToFullyQualified($names, 'p'));
        self::assertSame('App\\Pet\\Photo', NameContextVisitor::classNameToFullyQualified($names, 'P\\Photo'));
        self::assertSame('App\\Models\\Owner', NameContextVisitor::classNameToFullyQualified($names, 'Owner'));
        self::assertSame('App\\Models\\Tag', NameContextVisitor::classNameToFullyQualified($names, 'Label'));
        self::assertSame('App\\Http\\helper', NameContextVisitor::classNameToFullyQualified($names, 'helper'));
    }

    public function testResolvesAnUnimportedNameThroughTheNamespace(): void
    {
        $names = $this->namesOfFirstProperty(<<<'PHP'
            <?php

            namespace App\Http;

            final class Shelter
            {
                /** @var list<Pet> */
                public array $pets;
            }
            PHP);

        self::assertSame('App\\Http', $names->getNamespace()?->toString());
        self::assertSame('App\\Http\\Pet', NameContextVisitor::classNameToFullyQualified($names, 'Pet'));
        self::assertSame('App\\Http\\Models\\Pet', NameContextVisitor::classNameToFullyQualified($names, 'Models\\Pet'));
        self::assertSame('Acme\\Pet', NameContextVisitor::classNameToFullyQualified($names, '\\Acme\\Pet'));
        self::assertSame('App\\Http\\Pet', NameContextVisitor::classNameToFullyQualified($names, 'namespace\\Pet'));
        self::assertSame('self', NameContextVisitor::classNameToFullyQualified($names, 'self'));
    }

    public function testResolvesInTheGlobalNamespaceWithoutPrefix(): void
    {
        $names = $this->namesOfFirstProperty(<<<'PHP'
            <?php

            final class Shelter
            {
                /** @var list<Pet> */
                public array $pets;
            }
            PHP);

        self::assertSame('Pet', NameContextVisitor::classNameToFullyQualified($names, 'Pet'));
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

        self::assertSame('Acme\\Pet', NameContextVisitor::classNameToFullyQualified(NameContextVisitor::nodeToNameContext($properties[0]), 'Pet'));
        self::assertSame('App\\Http\\Pet', NameContextVisitor::classNameToFullyQualified(NameContextVisitor::nodeToNameContext($properties[1]), 'Pet'));
    }

    private function namesOfFirstProperty(string $code): NameContext
    {
        $property = (new NodeFinder())->findFirstInstanceOf($this->parse($code)->statements, Property::class);
        self::assertNotNull($property);

        return NameContextVisitor::nodeToNameContext($property);
    }

    private function parse(string $code): ParsedFile
    {
        file_put_contents($this->file, $code);
        $parsed = (new PhpFileToParsed())->fileToParsed($this->file);
        self::assertInstanceOf(ParsedFile::class, $parsed);

        return $parsed;
    }
}
