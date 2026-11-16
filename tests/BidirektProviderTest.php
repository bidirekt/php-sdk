<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use Attribute;
use Bidirekt\PhpSdk\BidirektProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;

final class BidirektProviderTest extends TestCase
{
    public function testStoresTheArgumentsOnAMethod(): void
    {
        $controller = new class {
            #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => 'Pet', 404 => null])]
            public function find(): void {}
        };

        $attribute = (new ReflectionObject($controller))->getMethod('find')->getAttributes(BidirektProvider::class)[0]->newInstance();

        self::assertSame('GET', $attribute->method);
        self::assertSame('/pets/*', $attribute->path);
        self::assertSame([200 => 'Pet', 404 => null], $attribute->responses);
        self::assertNull($attribute->request);
    }

    public function testTakesTheArgumentsInPositionalOrderAndRepeatsOnAClosure(): void
    {
        $handler = #[BidirektProvider('POST', '/pets', [201 => 'Pet'], 'NewPet')]
            #[BidirektProvider('GET', '/pets', [200 => ['list' => 'Pet', 'name' => 'PetCollection']])]
            function (): void {};

        $attributes = (new ReflectionFunction($handler))->getAttributes(BidirektProvider::class);
        $create = $attributes[0]->newInstance();
        $list = $attributes[1]->newInstance();

        self::assertSame(['POST', '/pets', [201 => 'Pet'], 'NewPet'], [$create->method, $create->path, $create->responses, $create->request]);
        self::assertSame([200 => ['list' => 'Pet', 'name' => 'PetCollection']], $list->responses);
    }

    public function testTargetsMethodsAndFunctionsRepeatably(): void
    {
        $target = (new ReflectionClass(BidirektProvider::class))->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE, $target->flags);
    }
}
