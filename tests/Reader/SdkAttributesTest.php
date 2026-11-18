<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use Bidirekt\PhpSdk\Reader\SdkAttribute;
use Bidirekt\PhpSdk\Reader\SdkAttributes;
use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class SdkAttributesTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/php-sdk-sdk-attributes-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testRecognizesTheAttributesByTheirFullyQualifiedName(): void
    {
        $parsed = $this->parse(<<<'PHP'
            <?php

            namespace App;

            use Bidirekt\PhpSdk\BidirektSchema;
            use Bidirekt\PhpSdk\BidirektField as Field;
            use Bidirekt\PhpSdk;

            #[BidirektSchema]
            #[Other]
            final class Pet
            {
                #[Field]
                public int $petId;

                public function __construct(
                    #[\Bidirekt\PhpSdk\BidirektField(name: 'pet_name')]
                    public string $name,
                ) {}

                #[PhpSdk\BidirektProvider(method: 'GET', path: '/pets', responses: [200 => ['Pet']])]
                #[PhpSdk\bidirektconsumer(provider: 'pets', method: 'GET', path: '/pets', responses: [200 => ['Pet']])]
                public function list(): array {}
            }
            PHP);
        $finder = new NodeFinder();

        self::assertSame([['BidirektSchema', 9]], $this->namesAndLines($parsed, $finder->findFirstInstanceOf($parsed->statements, Class_::class)));
        self::assertSame([['BidirektField', 13]], $this->namesAndLines($parsed, $finder->findFirstInstanceOf($parsed->statements, Property::class)));
        self::assertSame([['BidirektField', 17]], $this->namesAndLines($parsed, $finder->findFirstInstanceOf($parsed->statements, Param::class)));
        self::assertSame(
            [['BidirektProvider', 21], ['BidirektConsumer', 22]],
            $this->namesAndLines($parsed, $finder->findInstanceOf($parsed->statements, ClassMethod::class)[1]),
        );
        self::assertSame([], SdkAttributes::fileToMissingUseWarnings($parsed));
    }

    public function testIgnoresAnAttributeImportedFromAnotherNamespaceSilently(): void
    {
        $parsed = $this->parse(<<<'PHP'
            <?php

            namespace App;

            use Other\BidirektField;
            use Other\Annotations as Bidirekt;

            final class Pet
            {
                #[BidirektField]
                public int $petId;

                #[Bidirekt\BidirektField]
                #[\Other\BidirektField]
                #[Other\BidirektField]
                public string $name;
            }
            PHP);

        foreach ((new NodeFinder())->findInstanceOf($parsed->statements, Property::class) as $property) {
            self::assertSame([], SdkAttributes::elementToSdkAttributes($property, $parsed));
        }
        self::assertSame([], SdkAttributes::fileToMissingUseWarnings($parsed));
    }

    public function testWarnsAtTheAttributeWhenAnSdkShortNameHasNoUse(): void
    {
        $parsed = $this->parse(<<<'PHP'
            <?php

            namespace App\Models;

            use Bidirekt\PhpSdk\BidirektSchema;

            #[BidirektSchema]
            final class Pet
            {
                #[BidirektField]
                public int $petId;

                #[bidirektField]
                public string $name;
            }
            PHP);
        $property = (new NodeFinder())->findFirstInstanceOf($parsed->statements, Property::class);
        self::assertNotNull($property);

        self::assertSame([], SdkAttributes::elementToSdkAttributes($property, $parsed));
        self::assertEquals([
            new Diagnostic('#[BidirektField] resolves to "App\\Models\\BidirektField", so it is not read: write use Bidirekt\\PhpSdk\\BidirektField', new SourceLocation($this->file, 10)),
            new Diagnostic('#[BidirektField] resolves to "App\\Models\\bidirektField", so it is not read: write use Bidirekt\\PhpSdk\\BidirektField', new SourceLocation($this->file, 13)),
        ], SdkAttributes::fileToMissingUseWarnings($parsed));
    }

    public function testWarnsInTheGlobalNamespaceAndOnAClosure(): void
    {
        $parsed = $this->parse(<<<'PHP'
            <?php

            $app->get('/health', #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => null])] function () {});
            PHP);

        self::assertEquals([
            new Diagnostic('#[BidirektProvider] resolves to "BidirektProvider", so it is not read: write use Bidirekt\\PhpSdk\\BidirektProvider', new SourceLocation($this->file, 3)),
        ], SdkAttributes::fileToMissingUseWarnings($parsed));
    }

    public function testReadsPositionalArgumentsInTheOrderTheAttributeBindsThem(): void
    {
        $field = new BidirektField('petId');
        $attributes = [
            [new BidirektSchema('Pet', [$field]), ['Pet', [$field]]],
            [
                new BidirektField('pet_name', 'string', 'Item', 'Value', true, false, 'The name', ['card' => 'Card']),
                ['pet_name', 'string', 'Item', 'Value', true, false, 'The name', ['card' => 'Card']],
            ],
            [new BidirektProvider('GET', '/pets', [200 => 'Pet'], 'NewPet'), ['GET', '/pets', [200 => 'Pet'], 'NewPet']],
            [new BidirektConsumer('pets', 'GET', '/pets', [200 => 'Pet'], 'NewPet'), ['pets', 'GET', '/pets', [200 => 'Pet'], 'NewPet']],
        ];

        foreach ($attributes as [$attribute, $arguments]) {
            $class = get_class($attribute);
            $parameters = SdkAttributes::classToParameters($class);
            self::assertCount(count($arguments), $parameters, $class);
            foreach ($parameters as $position => $parameter) {
                self::assertSame($arguments[$position], $attribute->{$parameter}, sprintf('%s argument %d', $class, $position));
            }
        }
    }

    /**
     * @return list<array{string, int|null}>
     */
    private function namesAndLines(ParsedFile $parsed, ?Node $element): array
    {
        self::assertNotNull($element);

        return array_map(
            static fn(SdkAttribute $attribute): array => [$attribute->name, $attribute->location->line],
            SdkAttributes::elementToSdkAttributes($element, $parsed),
        );
    }

    private function parse(string $code): ParsedFile
    {
        file_put_contents($this->file, $code);
        $parsed = (new PhpFileToParsed())->codeToParsed($this->file, (string) file_get_contents($this->file));
        self::assertInstanceOf(ParsedFile::class, $parsed);

        return $parsed;
    }
}
