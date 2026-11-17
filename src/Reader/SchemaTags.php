<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use PhpParser\Node;

/**
 * The `@bidirekt schema` tag of each class-like and the `@bidirekt field` tag of each property and
 * parameter, keyed by the node key of the element the docblock is above.
 */
final class SchemaTags
{
    /** @var array<string, true> paths of the files whose tags are read */
    private array $readFiles = [];

    /**
     * @param array<string, CommentTag> $schemas
     * @param array<string, CommentTag> $fields
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
     * Adds the tags of a project file, read with the other project files'.
     */
    public function addProjectFile(ParsedFile $file, self $read): void
    {
        $this->readFiles[$file->path] = true;
        $this->schemas += $read->schemas;
        $this->fields += $read->fields;
        array_push($this->errors, ...$read->errors);
        array_push($this->warnings, ...$read->warnings);
    }

    /**
     * Reads the tags of a file outside the project, like a parent's in vendor/, once one of its classes is read.
     */
    public function addFile(ParsedFile $file): void
    {
        if (isset($this->readFiles[$file->path])) {
            return;
        }
        $this->readFiles[$file->path] = true;
        $tagged = FileToTaggedComments::fileToTaggedComments($file);
        $read = CommentsToSchemaTags::commentsToSchemaTags($tagged->comments);
        $this->schemas += $read->schemas;
        $this->fields += $read->fields;
        array_push($this->errors, ...$tagged->errors, ...$read->errors);
    }

    public function schemaTag(ParsedFile $file, Node $element): ?CommentTag
    {
        return $this->schemas[$file->nodeToKey($element)] ?? null;
    }

    public function fieldTag(ParsedFile $file, Node $element): ?CommentTag
    {
        return $this->fields[$file->nodeToKey($element)] ?? null;
    }
}
