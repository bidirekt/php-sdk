<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Composer\Autoload\ClassLoader;
use ParseError;

/**
 * Loads the maps Composer generates in `vendor/composer/` into a class loader: `autoload_psr4.php`,
 * `autoload_namespaces.php` and `autoload_classmap.php` only return arrays, so they are required, while
 * `autoload.php` and the `autoload_files` would run the project's code and never are. The `bin` loads the
 * SDK's own classes with it before any autoloader exists, so it uses nothing else of the SDK.
 */
final class VendorToClassLoader
{
    /**
     * A loader that is never registered, only asked for files.
     *
     * @return ClassLoader|Diagnostic|null null when the vendor folder is not installed
     */
    public static function vendorToClassLoader(string $vendorDirectory): ClassLoader|Diagnostic|null
    {
        if (!is_dir($vendorDirectory . '/composer')) {
            return null;
        }
        $loader = self::mapsToClassLoader($vendorDirectory);
        if (is_string($loader)) {
            return new Diagnostic('cannot read the Composer autoload map: run composer install again', new SourceLocation($loader, 1));
        }

        return $loader;
    }

    /**
     * @return ClassLoader|string the loader, or the map file that does not have the shape Composer generates
     */
    public static function mapsToClassLoader(string $vendorDirectory): ClassLoader|string
    {
        $loader = new ClassLoader($vendorDirectory);
        $psr4File = $vendorDirectory . '/composer/autoload_psr4.php';
        $psr4 = self::fileToMap($psr4File);
        if ($psr4 === null) {
            return $psr4File;
        }
        foreach ($psr4 as $prefix => $directories) {
            if (!is_array($directories) || ($prefix !== '' && !str_ends_with((string) $prefix, '\\'))) {
                return $psr4File;
            }
            $loader->setPsr4((string) $prefix, array_values(array_filter($directories, 'is_string')));
        }
        $psr0File = $vendorDirectory . '/composer/autoload_namespaces.php';
        $psr0 = self::fileToMap($psr0File);
        if ($psr0 === null) {
            return $psr0File;
        }
        foreach ($psr0 as $prefix => $directories) {
            if (!is_array($directories)) {
                return $psr0File;
            }
            $loader->set((string) $prefix, array_values(array_filter($directories, 'is_string')));
        }
        $classmapFile = $vendorDirectory . '/composer/autoload_classmap.php';
        $classmap = self::fileToMap($classmapFile);
        if ($classmap === null) {
            return $classmapFile;
        }
        // A classmap entry whose file is missing is left out, so the class is looked up by PSR-4 and PSR-0.
        $files = [];
        foreach ($classmap as $class => $file) {
            if (is_string($file) && is_file($file)) {
                $files[(string) $class] = $file;
            }
        }
        $loader->addClassMap($files);

        return $loader;
    }

    /**
     * Required in a closure of its own, so the map sees only the `$vendorDir` and `$baseDir` it declares,
     * as it does in Composer's loader. A missing map is empty.
     *
     * @return array<mixed>|null null when the file does not return an array
     */
    private static function fileToMap(string $file): ?array
    {
        if (!is_file($file)) {
            return [];
        }
        try {
            $map = (static function (string $mapFile): mixed {
                return require $mapFile;
            })($file);
        } catch (ParseError) {
            return null;
        }
        if (!is_array($map)) {
            return null;
        }

        return $map;
    }
}
