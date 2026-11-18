<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class Project
{
    /**
     * @param Composer|null $composer from the nearest `composer.json`, walking up from the directory
     */
    public function __construct(
        public string $directory,
        public ?Composer $composer,
    ) {}
}
