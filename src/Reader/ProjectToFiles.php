<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;

final class ProjectToFiles
{
    private const EXCLUDED_FOLDER_NAMES = ['node_modules'];

    /**
     * The project's `.php` files and those of the packages Composer installed from a `path` repository
     * outside the vendor folder.
     */
    public static function projectToFiles(Project $project): ProjectFiles
    {
        $warnings = [];
        $packages = self::composerToPathPackages($project->composer);
        $excluded = [...$packages, $project->directory . '/vendor'];
        if ($project->composer !== null) {
            $excluded = [...$packages, $project->composer->vendorDirectory, ...$project->composer->devPaths];
        }
        $files = self::directoryToPhpFiles($project->directory, self::pathsToReal($excluded), $warnings);
        foreach ($packages as $package) {
            $files = array_merge($files, self::packageToPhpFiles($package, $warnings));
        }
        $unique = [];
        foreach ($files as $file) {
            $unique[(string) realpath($file)] ??= $file;
        }
        $sorted = array_values($unique);
        sort($sorted, SORT_STRING);

        return new ProjectFiles($sorted, $warnings);
    }

    /**
     * @param list<Diagnostic> $warnings
     * @return list<string>
     */
    private static function packageToPhpFiles(string $package, array &$warnings): array
    {
        $excluded = [];
        if (is_file($package . '/composer.json')) {
            $composer = DirectoryToProject::composerFileToComposer($package . '/composer.json');
            if ($composer instanceof Composer) {
                $excluded = [$composer->vendorDirectory, ...$composer->devPaths];
            }
        }

        return self::directoryToPhpFiles($package, self::pathsToReal($excluded), $warnings);
    }

    /**
     * @param list<string> $excluded real paths
     * @param list<Diagnostic> $warnings
     * @param array<string, true> $visited real paths, so a symlink loop is read once
     * @return list<string>
     */
    private static function directoryToPhpFiles(string $directory, array $excluded, array &$warnings, array &$visited = []): array
    {
        $real = realpath($directory);
        if ($real === false || isset($visited[$real])) {
            return [];
        }
        $visited[$real] = true;
        $nestedVendor = self::directoryToVendor($directory);
        if ($nestedVendor !== null) {
            array_push($excluded, ...self::pathsToReal([$nestedVendor]));
        }
        $entries = @scandir($directory);
        if ($entries === false) {
            $warnings[] = new Diagnostic('cannot read the folder, so its files are not read: check that it can be read', new SourceLocation($directory));

            return [];
        }
        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (in_array(realpath($path), $excluded, true)) {
                continue;
            }
            if (is_dir($path)) {
                if (str_starts_with($entry, '.') || in_array($entry, self::EXCLUDED_FOLDER_NAMES, true)) {
                    continue;
                }
                $files = array_merge($files, self::directoryToPhpFiles($path, $excluded, $warnings, $visited));

                continue;
            }
            if (str_ends_with($entry, '.php') && is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * The vendor folder of the `composer.json` in the folder, which installs its own packages there.
     */
    private static function directoryToVendor(string $directory): ?string
    {
        if (!is_file($directory . '/composer.json')) {
            return null;
        }
        $composer = DirectoryToProject::composerFileToComposer($directory . '/composer.json');
        if ($composer instanceof Composer) {
            return $composer->vendorDirectory;
        }

        return $directory . '/vendor';
    }

    /**
     * @return list<string> real paths of the installed non-dev packages whose real path is outside the vendor folder
     */
    private static function composerToPathPackages(?Composer $composer): array
    {
        if ($composer === null) {
            return [];
        }
        $vendor = realpath($composer->vendorDirectory);
        if ($vendor === false) {
            return [];
        }
        $directories = [];
        foreach ($composer->installedPackages ?? [] as $package) {
            if ($package->installPath === null || $package->dev) {
                continue;
            }
            $real = realpath($composer->vendorDirectory . '/composer/' . $package->installPath);
            if ($real !== false && is_dir($real) && !str_starts_with(self::pathToSlashes($real) . '/', self::pathToSlashes($vendor) . '/')) {
                $directories[] = $real;
            }
        }

        return $directories;
    }

    /**
     * On Windows, realpath() answers with `\` separators.
     */
    private static function pathToSlashes(string $path): string
    {
        return strtr($path, '\\', '/');
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private static function pathsToReal(array $paths): array
    {
        $real = [];
        foreach ($paths as $path) {
            $resolved = realpath($path);
            if ($resolved !== false) {
                $real[] = $resolved;
            }
        }

        return $real;
    }
}
