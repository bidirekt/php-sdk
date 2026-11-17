<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * A class name written in a declaration: `extends`, `implements` or a trait `use`.
 */
final class NameSummary
{
    /**
     * @param string $written as written, before `use` and namespace resolve it
     */
    public function __construct(
        public string $resolved,
        public string $written,
        public SourceLocation $location,
    ) {}
}
