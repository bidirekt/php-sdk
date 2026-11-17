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

        $requiredPackages = [];
        foreach (['require', 'require-dev'] as $key) {
            if (!is_array($json[$key] ?? null)) {
                continue;
            }
            foreach (array_keys($json[$key]) as $package) {
                if (str_contains((string) $package, '/')) {
                    $requiredPackages[] = (string) $package;
                }
            }
        }

        $vendorDirectory = RelativePathToAbsolute::relativePathToAbsolute($directory, $vendor);

        return new Composer($vendorDirectory, $devPaths, $requiredPackages, self::vendorToInstalledPackages($vendorDirectory));
    }

    /**
     * @return list<InstalledPackage>|null
     */
    private static function vendorToInstalledPackages(string $vendorDirectory): ?array
    {
        $json = json_decode((string) @file_get_contents($vendorDirectory . '/composer/installed.json'), true);
        if (!is_array($json)) {
            return null;
        }
        // Composer 1 writes the list of packages alone.
        $packages = $json['packages'] ?? $json;
        $devNames = $json['dev-package-names'] ?? [];
        if (!is_array($packages) || !is_array($devNames)) {
            return null;
        }
        $installed = [];
        foreach ($packages as $package) {
            if (!is_array($package) || !is_string($package['name'] ?? null)) {
                continue;
            }
            $names = [strtolower($package['name'])];
            foreach (['provide', 'replace'] as $key) {
                if (is_array($package[$key] ?? null)) {
                    foreach (array_keys($package[$key]) as $name) {
                        $names[] = strtolower((string) $name);
                    }
                }
            }
            $installPath = $package['install-path'] ?? null;
            if (!is_string($installPath)) {
                $installPath = null;
            }
            $installed[] = new InstalledPackage($names, $installPath, in_array($package['name'], $devNames, true));
        }

        return $installed;
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

    /**
     * Goes up as Composer does, never to the home folder, the filesystem root or above.
     */
    private static function nearestComposerFile(string $directory): ?string
    {
        $home = self::homeDirectory();
        for ($current = $directory; ; $current = dirname($current)) {
            if (is_file($current . '/composer.json')) {
                return $current . '/composer.json';
            }
            $parent = dirname($current);
            if ($parent === $current || $parent === $home || dirname($parent) === $parent) {
                return null;
            }
        }
    }

    private static function homeDirectory(): ?string
    {
        foreach (['HOME', 'USERPROFILE'] as $variable) {
            $home = getenv($variable);
            if (!is_string($home) || $home === '') {
                continue;
            }
            $real = realpath($home);
            if ($real === false) {
                return null;
            }

            return $real;
        }

        return null;
    }
}
