<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * A project file that does not mention the SDK, kept unparsed: only the short names it may declare a class,
 * interface, trait or enum under, so a lookup of one of them knows which files to read.
 */
final class UnparsedFile
{
    /**
     * @param list<string> $names in lowercase, some maybe in a comment or a string
     */
    public function __construct(
        public string $path,
        public array $names,
    ) {}
}
