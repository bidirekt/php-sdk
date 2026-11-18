<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class ProvidedResource
{
    /**
     * @param list<Response> $responses
     */
    public function __construct(
        public string $method,
        public string $path,
        public ?Body $request,
        public array $responses,
        public SourceLocation $location,
    ) {}
}
