<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use LogicException;

/**
 * The files read so far. A parsed file takes about 500 bytes per token, so the files are kept parsed up to a
 * third of the memory limit and any other is parsed again each time it is read: a project file from the code
 * read the first time, so it reads the same, a vendor file from the disk.
 */
final class ParsedFiles
{
    private const BYTES_PER_KEPT_TOKEN = 600;

    private PhpFileToParsed $parser;

    private int $keptTokensLimit;

    /** @var array<string, string> real path => the path the file is read by, the first one it was asked by */
    private array $paths = [];

    /** @var array<string, string> path => code of a project file the SDK reads whole */
    private array $projectCode = [];

    /** @var array<string, ParsedFile> real path => the file */
    private array $kept = [];

    private int $keptTokens = 0;

    /** @var array<string, Diagnostic> real path => why the file cannot be read */
    private array $unreadable = [];

    public function __construct()
    {
        $this->parser = new PhpFileToParsed();
        $this->keptTokensLimit = intdiv(self::memoryLimitToBytes((string) ini_get('memory_limit')), 3 * self::BYTES_PER_KEPT_TOKEN);
    }

    /**
     * @return ParsedFile|list<DeclaredClass>|Diagnostic the file when it mentions the SDK, else the classes it declares
     */
    public function projectFileToRead(string $path): ParsedFile|array|Diagnostic
    {
        $code = PhpFileToParsed::fileToCode($path);
        if ($code instanceof Diagnostic) {
            return $code;
        }
        $read = $this->parser->projectCodeToRead($path, $code);
        if ($read instanceof ParsedFile) {
            $this->paths[self::pathToKey($path)] ??= $path;
            $this->projectCode[$path] = $code;
            $this->keep(self::pathToKey($path), $read);
        }

        return $read;
    }

    /**
     * A project file `projectFileToRead()` read whole.
     */
    public function projectFile(string $path): ParsedFile
    {
        $parsed = $this->pathToParsed($path);
        if ($parsed instanceof Diagnostic) {
            throw new LogicException(sprintf('the project file "%s" parsed before cannot be parsed again', $path));
        }

        return $parsed;
    }

    public function pathToParsed(string $path): ParsedFile|Diagnostic
    {
        $key = self::pathToKey($path);
        if (isset($this->kept[$key])) {
            return $this->kept[$key];
        }
        if (isset($this->unreadable[$key])) {
            return $this->unreadable[$key];
        }
        $path = $this->paths[$key] ??= $path;
        $code = $this->projectCode[$path] ?? PhpFileToParsed::fileToCode($path);
        if ($code instanceof Diagnostic) {
            $this->unreadable[$key] = $code;

            return $code;
        }
        $parsed = $this->parser->codeToParsed($path, $code);
        if ($parsed instanceof Diagnostic) {
            $this->unreadable[$key] = $parsed;

            return $parsed;
        }
        $this->keep($key, $parsed);

        return $parsed;
    }

    /**
     * The steps read the files in the same order, so the first ones are kept rather than the latest.
     */
    private function keep(string $key, ParsedFile $parsed): void
    {
        $tokens = count($parsed->tokens);
        if ($this->kept !== [] && $this->keptTokens + $tokens > $this->keptTokensLimit) {
            return;
        }
        $this->kept[$key] = $parsed;
        $this->keptTokens += $tokens;
    }

    /**
     * `-1` is no limit; `128M`, `1G`, `512K` or bytes otherwise.
     */
    private static function memoryLimitToBytes(string $limit): int
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
