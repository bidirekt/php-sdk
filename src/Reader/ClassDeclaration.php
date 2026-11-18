<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class ClassDeclaration
{
    public function __construct(
        public string $name,
        public ClassSummary $summary,
        public FileSummary $file,
    ) {}
}
