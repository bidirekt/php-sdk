<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class AutoloadMaps
{
    /**
     * @param array<string, list<string>> $psr4 namespace prefix => directories
     * @param array<string, list<string>> $psr0 prefix => directories
     * @param array<string, string> $classmap class => file
     */
    public function __construct(
        public array $psr4,
        public array $psr0,
        public array $classmap,
    ) {}
}
