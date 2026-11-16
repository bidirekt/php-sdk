<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use PhpParser\Node;

/**
 * The `@bidirekt schema` tag of each class-like and the `@bidirekt field` tag of each property and
 * parameter, keyed by the node id of the element the docblock is above.
 */
final class SchemaTags
{
    /**
     * @param array<int, CommentTag> $schemas
     * @param array<int, CommentTag> $fields
     * @param list<Diagnostic> $errors
     * @param list<Diagnostic> $warnings
     */
    public function __construct(
        public array $schemas = [],
        public array $fields = [],
        public array $errors = [],
        public array $warnings = [],
    ) {}

    public function schemaTag(Node $element): ?CommentTag
    {
        return $this->schemas[spl_object_id($element)] ?? null;
    }

    public function fieldTag(Node $element): ?CommentTag
    {
        return $this->fields[spl_object_id($element)] ?? null;
    }
}
