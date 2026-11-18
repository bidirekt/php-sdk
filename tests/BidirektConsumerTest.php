<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests;

use Attribute;
use Bidirekt\PhpSdk\BidirektConsumer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionObject;

final class BidirektConsumerTest extends TestCase
{
    public function testStoresTheArgumentsOnAMethod(): void
    {
        $client = new class {
            #[BidirektConsumer(provider: 'payments_api', method: 'POST', path: '/charges', request: 'NewCharge', responses: [201 => 'Charge', 402 => null])]
            public function charge(): void {}
        };

        $attribute = (new ReflectionObject($client))->getMethod('charge')->getAttributes(BidirektConsumer::class)[0]->newInstance();

        self::assertSame('payments_api', $attribute->provider);
        self::assertSame('POST', $attribute->method);
        self::assertSame('/charges', $attribute->path);
        self::assertSame([201 => 'Charge', 402 => null], $attribute->responses);
        self::assertSame('NewCharge', $attribute->request);
    }

    public function testTakesTheArgumentsInPositionalOrderAndRepeatsOnAClosure(): void
    {
        $handler = #[BidirektConsumer('payments_api', 'GET', '/charges/*', [200 => 'Charge'], null)]
            #[BidirektConsumer('payments_api', 'GET', '/charges', [200 => ['Charge']])]
            function (): void {};

        $attributes = (new ReflectionFunction($handler))->getAttributes(BidirektConsumer::class);
        $find = $attributes[0]->newInstance();
        $list = $attributes[1]->newInstance();

        self::assertSame(
            ['payments_api', 'GET', '/charges/*', [200 => 'Charge'], null],
            [$find->provider, $find->method, $find->path, $find->responses, $find->request],
        );
        self::assertSame([200 => ['Charge']], $list->responses);
    }

    public function testTargetsMethodsAndFunctionsRepeatably(): void
    {
        $target = (new ReflectionClass(BidirektConsumer::class))->getAttributes(Attribute::class)[0]->newInstance();

        self::assertSame(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE, $target->flags);
    }
}
