<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * Finds the file of a class in the vendor folder the way Composer's autoloader does: classmap, then
 * PSR-4, then PSR-0.
 */
final class ClassLocator
{
    public function __construct(
        private AutoloadMaps $maps,
    ) {}

    public function classToFile(string $class): ?string
    {
        $class = ltrim($class, '\\');
        $mapped = $this->maps->classmap[$class] ?? null;
        if ($mapped !== null && is_file($mapped)) {
            return $mapped;
        }
        $psr4Path = strtr($class, '\\', '/') . '.php';
        $found = $this->prefixesToFile($this->maps->psr4, $class, static fn(string $prefix): string => substr($psr4Path, strlen($prefix)));
        if ($found !== null) {
            return $found;
        }
        $separator = strrpos($class, '\\');
        $psr0Path = strtr($class, '_', '/') . '.php';
        if ($separator !== false) {
            $psr0Path = strtr(substr($class, 0, $separator + 1), '\\', '/') . strtr(substr($class, $separator + 1), '_', '/') . '.php';
        }

        return $this->prefixesToFile($this->maps->psr0, $class, static fn(): string => $psr0Path);
    }

    /**
     * @param array<string, list<string>> $prefixes
     * @param callable(string): string $prefixToRelativePath
     */
    private function prefixesToFile(array $prefixes, string $class, callable $prefixToRelativePath): ?string
    {
        uksort($prefixes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($prefixes as $prefix => $directories) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            foreach ($directories as $directory) {
                $file = $directory . '/' . $prefixToRelativePath($prefix);
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }
}
