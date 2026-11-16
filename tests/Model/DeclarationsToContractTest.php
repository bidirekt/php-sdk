<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Model;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\Body;
use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\DeclarationsToContract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\PrimitiveNode;
use Bidirekt\PhpSdk\Model\Property;
use Bidirekt\PhpSdk\Model\ProvidedResource;
use Bidirekt\PhpSdk\Model\RefNode;
use Bidirekt\PhpSdk\Model\Response;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PHPUnit\Framework\TestCase;

final class DeclarationsToContractTest extends TestCase
{
    public function testKeepsEveryNamedSchemaReferencedOrNot(): void
    {
        $owner = new NamedSchema('Owner', new ObjectNode([]), new SourceLocation('src/Owner.php', 1));

        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [self::pet(1), $owner]));

        self::assertSame([], $result->errors);
        self::assertEquals([self::pet(1), $owner], $result->contract->schemas);
    }

    public function testWritesAnIdenticalDerivedListUsedInSeveralPlacesOnce(): void
    {
        $result = DeclarationsToContract::declarationsToContract(new Contract(
            [self::listing('src/A.php', 'PetList'), self::listing('src/B.php', 'PetList', '/pets/recent')],
            [],
            [
                self::pet(1),
                new NamedSchema('PetList', self::petList(), new SourceLocation('src/A.php', 3)),
                new NamedSchema('PetList', self::petList(), new SourceLocation('src/B.php', 3)),
            ],
        ));

        self::assertSame([], $result->errors);
        self::assertSame(
            ['Pet', 'PetList'],
            array_map(static fn(NamedSchema $schema): string => $schema->name, $result->contract->schemas),
        );
    }

    public function testRejectsTwoSchemasWithTheSameNameAtBothPlaces(): void
    {
        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [self::pet(1), self::pet(8)]));

        $message = 'schema name "Pet" is declared more than once: give each schema a distinct name';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation('src/Pet.php', 1)),
                new Diagnostic($message, new SourceLocation('src/Pet.php', 8)),
            ],
            $result->errors,
        );
    }

    public function testRejectsTwoFieldsWithTheSameNameInOneSchemaAtBothPlaces(): void
    {
        $schema = new NamedSchema(
            'Pet',
            new ObjectNode([
                new Property('id', new PrimitiveNode('string'), new SourceLocation('src/Entity.php', 2)),
                new Property('id', new PrimitiveNode('integer'), new SourceLocation('src/Pet.php', 2)),
            ]),
            new SourceLocation('src/Pet.php', 1),
        );

        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [$schema]));

        $message = 'field "id" is declared more than once in schema "Pet": give each field a distinct name';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation('src/Entity.php', 2)),
                new Diagnostic($message, new SourceLocation('src/Pet.php', 2)),
            ],
            $result->errors,
        );
    }

    public function testRejectsTwoListsWithTheSameNameThatDifferAtBothPlaces(): void
    {
        $nullableList = self::petList();
        $nullableList->nullable = true;

        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [
            self::pet(1),
            new NamedSchema('PetList', self::petList(), new SourceLocation('src/A.php', 3)),
            new NamedSchema('PetList', $nullableList, new SourceLocation('src/B.php', 3)),
        ]));

        $message = 'lists named "PetList" differ: give each list a distinct name';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation('src/A.php', 3)),
                new Diagnostic($message, new SourceLocation('src/B.php', 3)),
            ],
            $result->errors,
        );
    }

    public function testRejectsADerivedListNameThatCollidesWithADeclaredSchema(): void
    {
        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [
            self::pet(1),
            new NamedSchema('PetList', new ObjectNode([]), new SourceLocation('src/PetList.php', 4)),
            new NamedSchema('PetList', self::petList(), new SourceLocation('src/A.php', 3)),
        ]));

        $message = 'list name "PetList" is also the name of another schema: give the list a distinct name';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation('src/PetList.php', 4)),
                new Diagnostic($message, new SourceLocation('src/A.php', 3)),
            ],
            $result->errors,
        );
    }

    public function testRejectsABodyThatReferencesANameNoSchemaHasAtItsLine(): void
    {
        $result = DeclarationsToContract::declarationsToContract(
            new Contract([self::listing('src/A.php', 'PetResponse')], [], [self::pet(1)]),
        );

        self::assertEquals(
            [
                new Diagnostic(
                    'schema "PetResponse" is not declared: reference a schema by its name in the contract',
                    new SourceLocation('src/A.php', 3),
                ),
            ],
            $result->errors,
        );
    }

    public function testAcceptsABodyWithoutASchema(): void
    {
        $result = DeclarationsToContract::declarationsToContract(new Contract([self::listing('src/A.php', null)], [], []));

        self::assertSame([], $result->errors);
    }

    public function testRejectsAReferenceInsideASchemaToANameNoSchemaHas(): void
    {
        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [
            new NamedSchema('PetList', self::petList(), new SourceLocation('src/A.php', 3)),
        ]));

        self::assertEquals(
            [
                new Diagnostic(
                    'schema "Pet" is not declared: reference a schema by its name in the contract',
                    new SourceLocation('src/A.php', 3),
                ),
            ],
            $result->errors,
        );
    }

    public function testRejectsAFieldReferenceToANameNoSchemaHasAtTheFieldLine(): void
    {
        $schema = new NamedSchema(
            'Pet',
            new ObjectNode([new Property('owner', new RefNode('Owner'), new SourceLocation('src/Pet.php', 5))]),
            new SourceLocation('src/Pet.php', 1),
        );

        $result = DeclarationsToContract::declarationsToContract(new Contract([], [], [$schema]));

        self::assertEquals(
            [
                new Diagnostic(
                    'schema "Owner" is not declared: reference a schema by its name in the contract',
                    new SourceLocation('src/Pet.php', 5),
                ),
            ],
            $result->errors,
        );
    }

    private static function petList(): ArrayNode
    {
        return new ArrayNode(new RefNode('Pet'));
    }

    private static function pet(int $line): NamedSchema
    {
        return new NamedSchema(
            'Pet',
            new ObjectNode([new Property('name', new PrimitiveNode('string'), new SourceLocation('src/Pet.php', $line + 1))]),
            new SourceLocation('src/Pet.php', $line),
        );
    }

    private static function listing(string $file, ?string $schema, string $path = '/pets'): ProvidedResource
    {
        return new ProvidedResource(
            'GET',
            $path,
            null,
            [new Response(200, new Body($schema, new SourceLocation($file, 3)))],
            new SourceLocation($file, 2),
        );
    }
}
