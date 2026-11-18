<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Property;
use Bidirekt\PhpSdk\Model\SchemaNode;
use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * The options a field declares, with `#[BidirektField]`, `new BidirektField(…)` or a tag.
 */
final class FieldOptions
{
    /**
     * @param SchemaNode|null $declared the type a tag's `type=`, `items=`, `values=` or `union=` declare, null when none does
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $optional = null,
        public ?bool $nullable = null,
        public ?SchemaNode $declared = null,
    ) {}

    /**
     * The field with the declared options applied to its type: an undeclared `nullable` keeps the type's.
     */
    public function nodeToProperty(string $memberName, SchemaNode $node, SourceLocation $location): Property
    {
        $node->nullable = $this->nullable ?? $node->nullable;
        $node->optional = $this->optional ?? false;
        $node->description = $this->description;

        return new Property(SchemaNaming::fieldToName($this->name, $memberName), $node, $location);
    }
}
