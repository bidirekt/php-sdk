<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class SourceLocation
{
    /**
     * @param int|null $line null for a whole file or folder
     */
    public function __construct(
        public string $file,
        public ?int $line = null,
    ) {}
}
