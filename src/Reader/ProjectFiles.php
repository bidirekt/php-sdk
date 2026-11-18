<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;

final class ProjectFiles
{
    /**
     * @param list<string> $paths in byte order
     * @param list<Diagnostic> $warnings one for each folder that cannot be read
     */
    public function __construct(
        public array $paths,
        public array $warnings,
    ) {}
}
