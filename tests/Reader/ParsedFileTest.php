<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class ParsedFileTest extends TestCase
{
    private const CODE = <<<'PHP'
        <?php

        #[First]
        #[Second]
        final class Pet
        {
            #[BidirektField]
            #[Other]
            public int $petId;

            public function __construct(
                #[BidirektField]
                public string $name,
            ) {}

            #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => ['Pet']])]
            public function list(): array
            {
                return [
                    #[BidirektProvider(method: 'GET', path: '/a', responses: [200 => null])]
                    static function () {},
                    #[BidirektProvider(method: 'GET', path: '/b', responses: [200 => null])] /* note */
                    fn () => null,
                    function () {},
                ];
            }
        }

        #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => null])]
        function health(): void {}
        PHP;

    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/php-sdk-parsed-file-' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($this->file, self::CODE);
    }

    protected function tearDown(): void
    {
        unlink($this->file);
    }

    public function testLocatesEachElementAtItsOwnLineBelowItsAttributes(): void
    {
        $parsed = (new PhpFileToParsed())->fileToParsed($this->file);
        self::assertInstanceOf(ParsedFile::class, $parsed);
        $finder = new NodeFinder();
        $closures = $finder->findInstanceOf($parsed->statements, Closure::class);

        self::assertSame(5, $this->lineOf($parsed, $finder->findFirstInstanceOf($parsed->statements, Class_::class)));
        self::assertSame(9, $this->lineOf($parsed, $finder->findFirstInstanceOf($parsed->statements, Property::class)));
        self::assertSame(13, $this->lineOf($parsed, $finder->findFirstInstanceOf($parsed->statements, Param::class)));
        self::assertSame(17, $this->lineOf($parsed, $finder->findInstanceOf($parsed->statements, ClassMethod::class)[1]));
        self::assertSame(21, $this->lineOf($parsed, $closures[0]));
        self::assertSame(23, $this->lineOf($parsed, $finder->findFirstInstanceOf($parsed->statements, ArrowFunction::class)));
        self::assertSame(24, $this->lineOf($parsed, $closures[1]));
        self::assertSame(30, $this->lineOf($parsed, $finder->findFirstInstanceOf($parsed->statements, Function_::class)));
    }

    public function testLocatesAnAttributeAtItsOwnLine(): void
    {
        $parsed = (new PhpFileToParsed())->fileToParsed($this->file);
        self::assertInstanceOf(ParsedFile::class, $parsed);
        $attributes = (new NodeFinder())->findInstanceOf($parsed->statements, Attribute::class);

        self::assertSame([3, 4, 7, 8], [
            $this->lineOf($parsed, $attributes[0]),
            $this->lineOf($parsed, $attributes[1]),
            $this->lineOf($parsed, $attributes[2]),
            $this->lineOf($parsed, $attributes[3]),
        ]);
    }

    public function testLocatesAnyOtherNodeAtItsStartLine(): void
    {
        $parsed = (new PhpFileToParsed())->fileToParsed($this->file);
        self::assertInstanceOf(ParsedFile::class, $parsed);

        self::assertSame(19, $this->lineOf($parsed, (new NodeFinder())->findFirstInstanceOf($parsed->statements, Return_::class)));
        self::assertSame($this->file, $parsed->lineToLocation(3)->file);
    }

    public function testReadsTheDocblockAboveTheAttributesOrBetweenThemAndTheElement(): void
    {
        file_put_contents($this->file, <<<'PHP'
            <?php

            final class Pet
            {
                /** @var list<int> */
                #[BidirektField]
                public array $above;

                #[BidirektField]
                /** @var list<string> */
                public array $below;

                public array $none;
            }
            PHP);
        $parsed = (new PhpFileToParsed())->fileToParsed($this->file);
        self::assertInstanceOf(ParsedFile::class, $parsed);
        $properties = (new NodeFinder())->findInstanceOf($parsed->statements, Property::class);

        self::assertSame(
            ['/** @var list<int> */', '/** @var list<string> */', null],
            array_map(static fn(Property $property): ?string => $parsed->docCommentOf($property), $properties),
        );
    }

    private function lineOf(ParsedFile $parsed, ?Node $node): int
    {
        self::assertNotNull($node);
        $location = $parsed->locationOf($node);
        self::assertSame($this->file, $location->file);

        return $location->line;
    }
}
