<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

final class PathToShown
{
    /**
     * The path relative to the cwd, with `/` separators; both are absolute.
     */
    public static function pathToShown(string $path, string $cwd): string
    {
        $pathSegments = self::pathToSegments($path);
        $cwdSegments = self::pathToSegments($cwd);
        $common = 0;
        while (isset($pathSegments[$common], $cwdSegments[$common]) && $pathSegments[$common] === $cwdSegments[$common]) {
            $common++;
        }
        $up = array_fill(0, count($cwdSegments) - $common, '..');

        return implode('/', [...$up, ...array_slice($pathSegments, $common)]);
    }

    /**
     * @return list<string>
     */
    private static function pathToSegments(string $path): array
    {
        return array_values(array_filter(explode('/', str_replace('\\', '/', $path)), static fn(string $segment): bool => $segment !== ''));
    }
}
