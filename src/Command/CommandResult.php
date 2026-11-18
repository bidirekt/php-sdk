<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

final class CommandResult
{
    /**
     * @param 0|1 $exitCode
     */
    public function __construct(
        public int $exitCode,
        public string $stderr,
    ) {}
}
