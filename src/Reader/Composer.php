<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class Composer
{
    /**
     * @param list<string> $devPaths absolute paths of the `autoload-dev` entries
     * @param list<string> $requiredPackages the packages of `require` and `require-dev`, platform ones left out
     */
    public function __construct(
        public string $vendorDirectory,
        public array $devPaths,
        public array $requiredPackages = [],
    ) {}
}
