<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;

/**
 * The files read so far, each kept as its summary, so a file is parsed once: a project file when it is read,
 * any other when a lookup first reaches it. A summary is far smaller than the file's tree, which is dropped.
 */
final class ParsedFiles
{
    private PhpFileToParsed $parser;

    /** @var array<string, string> real path => the path the file is read by, the first one it was asked by */
    private array $paths = [];

    /** @var array<string, FileSummary|Diagnostic> real path => the file's summary, or why it cannot be read */
    private array $summaries = [];

    public function __construct()
    {
        $this->parser = new PhpFileToParsed();
    }

    /**
     * @return ParsedFile|UnparsedFile|Diagnostic the file when it mentions the SDK, else the names it may declare
     */
    public function projectFileToRead(string $path): ParsedFile|UnparsedFile|Diagnostic
    {
        $code = PhpFileToParsed::fileToCode($path);
        if ($code instanceof Diagnostic) {
            return $code;
        }

        return $this->parser->projectCodeToRead($path, $code);
    }

    /**
     * Keeps the summary of a project file `projectFileToRead()` parsed.
     *
     * @param SchemaTags $tags the file's own schema and field tags
     */
    public function addProjectFile(ParsedFile $file, SchemaTags $tags): FileSummary
    {
        $key = self::pathToKey($file->path);
        $this->paths[$key] ??= $file->path;

        return $this->summaries[$key] = ParsedFileToSummary::parsedFileToSummary($file, $tags);
    }

    public function pathToSummary(string $path): FileSummary|Diagnostic
    {
        $key = self::pathToKey($path);
        if (isset($this->summaries[$key])) {
            return $this->summaries[$key];
        }
        $path = $this->paths[$key] ??= $path;
        $code = PhpFileToParsed::fileToCode($path);
        if ($code instanceof Diagnostic) {
            return $this->summaries[$key] = $code;
        }
        $parsed = $this->parser->codeToParsed($path, $code);
        if ($parsed instanceof Diagnostic) {
            return $this->summaries[$key] = $parsed;
        }

        return $this->summaries[$key] = ParsedFileToSummary::parsedFileToSummary($parsed, SchemaTags::parsedFileToTags($parsed));
    }

    /**
     * The classes a file declares, read from its tokens, none when it cannot be read.
     *
     * @return list<DeclaredClass>
     */
    public static function pathToDeclaredClasses(string $path): array
    {
        $code = PhpFileToParsed::fileToCode($path);
        if ($code instanceof Diagnostic) {
            return [];
        }

        return CodeToDeclaredClasses::codeToDeclaredClasses($path, $code);
    }

    /**
     * `-1` is no limit; `128M`, `1G`, `512K` or bytes otherwise.
     */
    public static function memoryLimitToBytes(string $limit): int
    {
        if (preg_match('/^\s*(\d+)\s*([kmg]?)\s*$/i', $limit, $match) !== 1) {
            return PHP_INT_MAX;
        }
        $units = ['' => 1, 'k' => 1024, 'm' => 1024 ** 2, 'g' => 1024 ** 3];

        return (int) min(PHP_INT_MAX, (int) $match[1] * $units[strtolower($match[2])]);
    }

    private static function pathToKey(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            return $path;
        }

        return $real;
    }
}
