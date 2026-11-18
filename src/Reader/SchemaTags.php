<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;

/**
 * The `@bidirekt schema` tag of each class-like and the `@bidirekt field` tag of each property and
 * parameter of one file, keyed by the node key of the element the docblock is above; or, for the whole
 * read, the errors and warnings of the tags of the files read.
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
     * Adds the errors and warnings of a project file's tags, read with the other project files'.
     */
    public function addProjectFile(FileSummary $file): void
    {
        $this->readFiles[$file->path] = true;
        array_push($this->errors, ...$file->tags->errors);
        array_push($this->warnings, ...$file->tags->warnings);
    }

    /**
     * Adds the errors of the tags of a file outside the project, like a parent's in vendor/, once one of its
     * classes is read: not their warnings.
     */
    public function addFile(FileSummary $file): void
    {
        if (isset($this->readFiles[$file->path])) {
            return;
        }
        $this->readFiles[$file->path] = true;
        array_push($this->errors, ...$file->tags->errors);
    }

    public function schemaTag(string $key): ?CommentTag
    {
        return $this->schemas[$key] ?? null;
    }

    public function fieldTag(string $key): ?CommentTag
    {
        return $this->fields[$key] ?? null;
    }

    /**
     * The tags of a parsed file, its tag errors first.
     */
    public static function parsedFileToTags(ParsedFile $file): self
    {
        $tagged = FileToTaggedComments::fileToTaggedComments($file);
        $read = CommentsToSchemaTags::commentsToSchemaTags($tagged->comments);
        $read->errors = [...$tagged->errors, ...$read->errors];

        return $read;
    }
}
