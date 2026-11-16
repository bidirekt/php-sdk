<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class ProjectToFiles
{
    private const EXCLUDED_FOLDER_NAMES = ['vendor', 'node_modules'];

    /**
     * The project's `.php` files and those of the packages Composer installed from a `path` repository
     * outside the vendor folder, in byte order.
     *
     * @return list<string>
     */
    public static function projectToFiles(Project $project): array
    {
        $packages = self::composerToPathPackages($project->composer);
        $excluded = $packages;
        if ($project->composer !== null) {
            array_push($excluded, $project->composer->vendorDirectory, ...$project->composer->devPaths);
        }
        $files = self::directoryToPhpFiles($project->directory, self::pathsToReal($excluded));
        foreach ($packages as $package) {
            $files = array_merge($files, self::packageToPhpFiles($package));
        }
        $unique = [];
        foreach ($files as $file) {
            $unique[(string) realpath($file)] ??= $file;
        }
        $sorted = array_values($unique);
        sort($sorted, SORT_STRING);

        return $sorted;
    }

    /**
     * @return list<string>
     */
    private static function packageToPhpFiles(string $package): array
    {
        $excluded = [];
        if (is_file($package . '/composer.json')) {
            $composer = DirectoryToProject::composerFileToComposer($package . '/composer.json');
            if ($composer instanceof Composer) {
                $excluded = [$composer->vendorDirectory, ...$composer->devPaths];
            }
        }

        return self::directoryToPhpFiles($package, self::pathsToReal($excluded));
    }

    /**
     * @param list<string> $excluded real paths
     * @param array<string, true> $visited real paths, so a symlink loop is read once
     * @return list<string>
     */
    private static function directoryToPhpFiles(string $directory, array $excluded, array &$visited = []): array
    {
        $real = realpath($directory);
        if ($real === false || isset($visited[$real])) {
            return [];
        }
        $visited[$real] = true;
        $entries = scandir($directory);
        if ($entries === false) {
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
                $files = array_merge($files, self::directoryToPhpFiles($path, $excluded, $visited));

                continue;
            }
            if (str_ends_with($entry, '.php') && is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
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
        $installed = json_decode((string) @file_get_contents($composer->vendorDirectory . '/composer/installed.json'), true);
        if ($vendor === false || !is_array($installed)) {
            return [];
        }
        $packages = $installed['packages'] ?? $installed;
        $devNames = $installed['dev-package-names'] ?? [];
        if (!is_array($packages) || !is_array($devNames)) {
            return [];
        }
        $directories = [];
        foreach ($packages as $package) {
            if (!is_array($package) || !is_string($package['install-path'] ?? null) || in_array($package['name'] ?? null, $devNames, true)) {
                continue;
            }
            $real = realpath($composer->vendorDirectory . '/composer/' . $package['install-path']);
            if ($real !== false && is_dir($real) && !str_starts_with($real . '/', $vendor . '/')) {
                $directories[] = $real;
            }
        }

        return $directories;
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
