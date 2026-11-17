<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class TraitUseSummary
{
    /**
     * @param list<NameSummary> $traits
     */
    public function __construct(
        public array $traits,
    ) {}
}
