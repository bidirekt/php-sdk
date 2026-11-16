<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SchemaNode;

/**
 * The `key=value` options of a `@bidirekt schema` or `@bidirekt field` tag.
 */
final class TagFieldOptions
{
    /**
     * @param SchemaNode|null $declared the type `type=`, `items=`, `values=` or `union=` declare, null when none does
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $optional = null,
        public ?bool $nullable = null,
        public ?SchemaNode $declared = null,
    ) {}
}
