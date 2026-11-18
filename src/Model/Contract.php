<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class Contract
{
    /**
     * @param list<ProvidedResource> $provides
     * @param list<ConsumedResource> $consumes
     * @param list<NamedSchema> $schemas
     */
    public function __construct(
        public array $provides = [],
        public array $consumes = [],
        public array $schemas = [],
    ) {}
}
