<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use Attribute;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;

final class BidirektSchemaTest extends TestCase
{
    public function testWorksBareOnAClass(): void
    {
        $schema = new #[BidirektSchema] class {};

        $attribute = (new ReflectionObject($schema))->getAttributes(BidirektSchema::class)[0]->newInstance();

        self::assertNull($attribute->name);
        self::assertSame([], $attribute->fields);
    }

    public function testTakesTheNameFirstAndRepeatsOnAHandler(): void
    {
        $handler = #[BidirektSchema('Health')] #[BidirektSchema(name: 'Uptime')] function (): void {};

        $attributes = (new ReflectionFunction($handler))->getAttributes(BidirektSchema::class);

        self::assertSame('Health', $attributes[0]->newInstance()->name);
        self::assertSame('Uptime', $attributes[1]->newInstance()->name);
    }

    public function testStoresTheFieldsItIsGiven(): void
    {
        $fields = [new BidirektField('status', type: 'string')];

        self::assertSame($fields, (new BidirektSchema('Health', $fields))->fields);
    }

    public function testTargetsClassesMethodsAndFunctionsRepeatably(): void
    {
        $target = (new ReflectionClass(BidirektSchema::class))->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(
            Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE,
            $target->flags,
        );
    }
}
