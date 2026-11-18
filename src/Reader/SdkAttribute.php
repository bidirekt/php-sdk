<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

final class SdkAttribute
{
    /**
     * @param string $name the SDK attribute's short name, as the SDK declares it ("BidirektField")
     * @param list<ArgumentSummary> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
        public SourceLocation $location,
    ) {}
}
