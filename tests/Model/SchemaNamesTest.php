<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Model;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\RefNode;
use Bidirekt\PhpSdk\Model\SchemaNames;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PHPUnit\Framework\TestCase;

final class SchemaNamesTest extends TestCase
{
    public function testNamesAListAfterItsSchema(): void
    {
        self::assertSame('PetList', SchemaNames::schemaToListName('Pet'));
    }

    public function testDerivesTheListNameFromTheSchemaOfItsItems(): void
    {
        $at = new SourceLocation('src/Pets.php', 7);

        self::assertEquals(
            new NamedSchema('PetList', new ArrayNode(new RefNode('Pet')), $at),
            SchemaNames::listToNamedSchema('Pet', null, $at),
        );
    }

    public function testUsesTheDeclaredListName(): void
    {
        $at = new SourceLocation('src/Pets.php', 7);

        self::assertEquals(
            new NamedSchema('PetCollection', new ArrayNode(new RefNode('Pet')), $at),
            SchemaNames::listToNamedSchema('Pet', 'PetCollection', $at),
        );
    }
}
