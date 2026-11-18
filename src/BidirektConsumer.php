<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
final class BidirektConsumer
{
    /**
     * @param array<int|string, string|array<int|string, string>|null> $responses
     * @param string|array<int|string, string>|null $request
     */
    public function __construct(
        public string $provider,
        public string $method,
        public string $path,
        public array $responses,
        public string|array|null $request = null,
    ) {}
}
