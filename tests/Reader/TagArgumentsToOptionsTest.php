<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\MapNode;
use Bidirekt\PhpSdk\Model\PrimitiveNode;
use Bidirekt\PhpSdk\Model\RefNode;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Model\UnionNode;
use Bidirekt\PhpSdk\Reader\CommentTag;
use Bidirekt\PhpSdk\Reader\TagArgumentsToOptions;
use Bidirekt\PhpSdk\Reader\TagFieldOptions;
use PHPUnit\Framework\TestCase;

final class TagArgumentsToOptionsTest extends TestCase
{
    public function testReadsEachOption(): void
    {
        $options = self::read(['name=pet_name', 'optional=true', 'nullable=false', 'description="Shown on the card"', 'type=Pet']);

        self::assertInstanceOf(TagFieldOptions::class, $options);
        self::assertSame(['pet_name', 'Shown on the card', true, false], [$options->name, $options->description, $options->optional, $options->nullable]);
        self::assertInstanceOf(RefNode::class, $options->declared);
        self::assertSame('Pet', $options->declared->schema);
    }

    public function testReadsArraysAndMapsWithTheContractKeys(): void
    {
        $array = self::read(['type=array', 'items=integer']);
        $map = self::read(['type=map', 'values=Pet']);
        $union = self::read(['union={card: Card, pix:Pix}']);

        self::assertInstanceOf(TagFieldOptions::class, $array);
        self::assertInstanceOf(ArrayNode::class, $array->declared);
        self::assertInstanceOf(PrimitiveNode::class, $array->declared->items);
        self::assertInstanceOf(TagFieldOptions::class, $map);
        self::assertInstanceOf(MapNode::class, $map->declared);
        self::assertInstanceOf(RefNode::class, $map->declared->values);
        self::assertInstanceOf(TagFieldOptions::class, $union);
        self::assertInstanceOf(UnionNode::class, $union->declared);
        self::assertSame(['card', 'pix'], array_map(static fn($member): string => $member->key, $union->declared->members));
    }

    public function testPointsTheShortFormsToTheContractKeys(): void
    {
        self::assertSame('cannot read "type=Pet[]" in "@bidirekt field": write type=array items=Pet', self::message(['type=Pet[]']));
        self::assertSame('cannot read "type=map<string>" in "@bidirekt field": write type=map values=string', self::message(['type=map<string>']));
    }

    public function testDoesNotNestItemsOrValues(): void
    {
        self::assertSame(
            'cannot read "items=array" in "@bidirekt field": write "items=" followed by string, integer, float, number, boolean or a schema name',
            self::message(['type=array', 'items=array']),
        );
        self::assertSame(
            'type=map needs "values=" in "@bidirekt field": write the type of its values, as in values=Pet',
            self::message(['type=map']),
        );
        self::assertSame(
            '"values=" is declared without type=map in "@bidirekt field": write type=map or remove "values="',
            self::message(['values=string']),
        );
    }

    public function testErrorsOnTypeWithUnionAndOnAKeyTheTagDoesNotTake(): void
    {
        self::assertSame('declare either "type=" or "union=" in "@bidirekt field", not both', self::message(['type=Card', 'union={card: Card}']));
        self::assertSame(
            'cannot read "Pet" in "@bidirekt schema": write "name=<name>"',
            self::message(['Pet'], 'schema', ['name']),
        );
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $keys
     */
    private static function read(array $arguments, string $keyword = 'field', array $keys = TagArgumentsToOptions::FIELD_KEYS): TagFieldOptions|Diagnostic
    {
        $tag = new CommentTag($keyword, $arguments, new SourceLocation('pets.php', 3));

        return TagArgumentsToOptions::argumentsToOptions($tag, $arguments, $keys);
    }

    /**
     * @param list<string> $arguments
     * @param list<string> $keys
     */
    private static function message(array $arguments, string $keyword = 'field', array $keys = TagArgumentsToOptions::FIELD_KEYS): string
    {
        $read = self::read($arguments, $keyword, $keys);
        self::assertInstanceOf(Diagnostic::class, $read);

        return $read->message;
    }
}
