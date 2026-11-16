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
    /** @var array<int, true> ids of the files whose tags are read */
    private array $readFiles = [];

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

    /**
     * @param list<ParsedFile> $files the files whose tags these are
     */
    public function addReadFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->readFiles[spl_object_id($file)] = true;
        }
    }

    /**
     * Reads the tags of a file outside the project, like a parent's in vendor/, once one of its classes is read.
     */
    public function addFile(ParsedFile $file): void
    {
        if (isset($this->readFiles[spl_object_id($file)])) {
            return;
        }
        $this->readFiles[spl_object_id($file)] = true;
        $tagged = FilesToTaggedComments::filesToTaggedComments([$file]);
        $read = CommentsToSchemaTags::commentsToSchemaTags($tagged->comments);
        $this->schemas += $read->schemas;
        $this->fields += $read->fields;
        array_push($this->errors, ...$tagged->errors, ...$read->errors);
    }

    public function schemaTag(Node $element): ?CommentTag
    {
        return $this->schemas[spl_object_id($element)] ?? null;
    }

    public function fieldTag(Node $element): ?CommentTag
    {
        return $this->fields[spl_object_id($element)] ?? null;
    }
}
