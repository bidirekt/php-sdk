<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class FileNodesVisitorTest extends TestCase
{
    private const FILE = '/project/routes.php';

    public function testDropsTheBodyOfAFunctionThatHoldsNothingTheReaderReads(): void
    {
        $parsed = $this->parse(<<<'PHP'
            <?php

            final class Shelter
            {
                #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => null])]
                public function pets(): array
                {
                    return array_map(fn ($pet) => $pet, [Pet::class]);
                }
            }
            PHP);

        $method = (new NodeFinder())->findFirstInstanceOf($parsed->statements, ClassMethod::class);
        self::assertNotNull($method);
        self::assertSame([], $method->stmts);
        self::assertCount(1, $parsed->nodes->functionLikes);
        self::assertCount(1, $parsed->nodes->attributes);
    }

    /**
     * @return array<string, array{string}>
     */
    public function bodiesThatHoldWhatIsRead(): array
    {
        return [
            'an attribute' => ["\$app->get('/pets', #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => null])] fn () => null);"],
            'a tag' => ["/** @Bidirekt provides GET /pets */\n    \$app->get('/pets', fn () => null);"],
            'a class declaration' => ['class /* declared on demand */ Pet {}'],
        ];
    }

    /**
     * @dataProvider bodiesThatHoldWhatIsRead
     */
    public function testKeepsTheBodyOfAFunctionThatHoldsWhatTheReaderReads(string $body): void
    {
        $parsed = $this->parse("<?php\n\nfunction routes(\$app): void\n{\n    " . $body . "\n}\n");

        $function = (new NodeFinder())->findFirstInstanceOf($parsed->statements, Function_::class);
        self::assertNotNull($function);
        self::assertNotSame([], $function->stmts);
    }

    private function parse(string $code): ParsedFile
    {
        $parsed = (new PhpFileToParsed())->codeToParsed(self::FILE, $code);
        self::assertInstanceOf(ParsedFile::class, $parsed);

        return $parsed;
    }
}
