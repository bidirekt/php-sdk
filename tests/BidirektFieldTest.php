<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use Attribute;
use Bidirekt\PhpSdk\BidirektField;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionObject;

final class BidirektFieldTest extends TestCase
{
    public function testWorksBareOnAPropertyAndAPromotedParameter(): void
    {
        $schema = new class (1) {
            #[BidirektField]
            public string $name = 'Rex';

            public function __construct(
                #[BidirektField]
                public int $petId,
            ) {}
        };
        $reflection = new ReflectionObject($schema);

        $onProperty = $reflection->getProperty('name')->getAttributes(BidirektField::class)[0]->newInstance();
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $onParameter = $constructor->getParameters()[0]->getAttributes(BidirektField::class)[0]->newInstance();

        self::assertEquals(new BidirektField(), $onProperty);
        self::assertEquals(new BidirektField(), $onParameter);
    }

    public function testTakesTheNameFirstAndTheRestByName(): void
    {
        $schema = new class {
            #[BidirektField('petId', type: 'integer')]
            public int $id = 1;
        };

        $attribute = (new ReflectionObject($schema))->getProperty('id')->getAttributes(BidirektField::class)[0]->newInstance();

        self::assertEquals(new BidirektField('petId', type: 'integer'), $attribute);
        self::assertSame('petId', $attribute->name);
        self::assertSame('integer', $attribute->type);
    }

    public function testStoresEveryArgumentInItsPositionalOrder(): void
    {
        $schema = new class {
            /** @var list<int> */
            #[BidirektField('tags', 'array', ['type' => 'array', 'items' => 'integer'], 'string', true, false, 'Shown on the card', ['card' => 'Card', 'pix' => 'Pix'])]
            public array $tags = [];
        };

        $attribute = (new ReflectionObject($schema))->getProperty('tags')->getAttributes(BidirektField::class)[0]->newInstance();

        self::assertSame('tags', $attribute->name);
        self::assertSame('array', $attribute->type);
        self::assertSame(['type' => 'array', 'items' => 'integer'], $attribute->items);
        self::assertSame('string', $attribute->values);
        self::assertTrue($attribute->optional);
        self::assertFalse($attribute->nullable);
        self::assertSame('Shown on the card', $attribute->description);
        self::assertSame(['card' => 'Card', 'pix' => 'Pix'], $attribute->union);
    }

    public function testTargetsPropertiesAndParameters(): void
    {
        $target = (new ReflectionClass(BidirektField::class))->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER, $target->flags);
    }
}
