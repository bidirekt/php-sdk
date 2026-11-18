<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * A package of the vendor folder's `composer/installed.json`.
 */
final class InstalledPackage
{
    /**
     * @param list<string> $names its name and the names of the packages it provides or replaces, in lowercase
     * @param string|null $installPath its folder, relative to the vendor folder's `composer` folder
     * @param bool $dev whether only `require-dev` needs it
     */
    public function __construct(
        public array $names,
        public ?string $installPath,
        public bool $dev,
    ) {}
}
