<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class RelativePathToAbsolute
{
    public static function relativePathToAbsolute(string $base, string $path): string
    {
        $joined = $base . '/' . $path;
        if (str_starts_with($path, '/')) {
            $joined = $path;
        }
        $segments = [];
        foreach (explode('/', $joined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return '/' . implode('/', $segments);
    }
}
