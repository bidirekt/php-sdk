<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class Composer
{
    /**
     * @param list<string> $devPaths absolute paths of the `autoload-dev` entries
     */
    public function __construct(
        public string $directory,
        public string $vendorDirectory,
        public array $devPaths,
    ) {}
}
