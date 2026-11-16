<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;

final class DirectoryToProject
{
    public static function directoryToProject(string $directory): Project|Diagnostic
    {
        $real = realpath($directory);
        if ($real === false || !is_dir($real)) {
            return new Diagnostic(sprintf('cannot read the project folder "%s": pass an existing folder', $directory));
        }
        $composerFile = self::nearestComposerFile($real);
        if ($composerFile === null) {
            return new Project($real, null);
        }
        $composer = self::composerFileToComposer($composerFile);
        if ($composer instanceof Diagnostic) {
            return $composer;
        }

        return new Project($real, $composer);
    }

    public static function composerFileToComposer(string $file): Composer|Diagnostic
    {
        $json = json_decode((string) @file_get_contents($file), true);
        if (!is_array($json)) {
            return new Diagnostic('cannot read the file as JSON: fix composer.json', new SourceLocation($file, 1));
        }
        $directory = dirname($file);
        $vendor = 'vendor';
        if (is_array($json['config'] ?? null) && is_string($json['config']['vendor-dir'] ?? null)) {
            $vendor = $json['config']['vendor-dir'];
        }
        $devPaths = [];
        foreach (self::autoloadEntryToPaths($json['autoload-dev'] ?? []) as $path) {
            $devPaths[] = RelativePathToAbsolute::relativePathToAbsolute($directory, $path);
        }

        return new Composer($directory, RelativePathToAbsolute::relativePathToAbsolute($directory, $vendor), $devPaths);
    }

    /**
     * @return list<string>
     */
    private static function autoloadEntryToPaths(mixed $entry): array
    {
        if (is_string($entry)) {
            return [$entry];
        }
        if (!is_array($entry)) {
            return [];
        }
        $paths = [];
        foreach ($entry as $value) {
            array_push($paths, ...self::autoloadEntryToPaths($value));
        }

        return $paths;
    }

    private static function nearestComposerFile(string $directory): ?string
    {
        for ($current = $directory; ; $current = dirname($current)) {
            if (is_file($current . '/composer.json')) {
                return $current . '/composer.json';
            }
            if (dirname($current) === $current) {
                return null;
            }
        }
    }
}
