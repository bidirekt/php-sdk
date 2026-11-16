<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class RelativePathToAbsolute
{
    /** A `/` root, a Windows drive (`C:\`) or a UNC share (`\\server\share`), with either separator. */
    private const ROOT_PATTERN = '~^(?:[A-Za-z]:[\\\\/]|[\\\\/]{2}[^\\\\/]+[\\\\/][^\\\\/]+(?:[\\\\/]|$)|[\\\\/])~';

    public static function relativePathToAbsolute(string $base, string $path): string
    {
        $joined = $path;
        if (preg_match(self::ROOT_PATTERN, $path) !== 1) {
            $joined = $base . '/' . $path;
        }
        $root = '/';
        if (preg_match(self::ROOT_PATTERN, $joined, $match) === 1) {
            $root = rtrim(strtr($match[0], '\\', '/'), '/') . '/';
            $joined = substr($joined, strlen($match[0]));
        }
        $segments = [];
        foreach ((array) preg_split('~[\\\\/]~', $joined) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);

                continue;
            }
            $segments[] = $segment;
        }

        return $root . implode('/', $segments);
    }
}
