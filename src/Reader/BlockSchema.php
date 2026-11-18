<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Property;
use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * A schema a handler's docblock declares with `@bidirekt schema <name>`, while its fields are read.
 */
final class BlockSchema
{
    /**
     * @param list<Property> $properties
     * @param bool $hasUnreadField whether a field below it could not be read, so its emptiness is not reported
     */
    public function __construct(
        public SourceLocation $location,
        public string $name = '',
        public array $properties = [],
        public bool $hasUnreadField = false,
    ) {}
}
