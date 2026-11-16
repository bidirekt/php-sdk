<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class NamesToChoice
{
    /**
     * How messages list the choices: `a, b or c`.
     *
     * @param list<string> $names
     */
    public static function namesToChoice(array $names): string
    {
        if (count($names) < 2) {
            return implode('', $names);
        }

        return implode(', ', array_slice($names, 0, -1)) . ' or ' . $names[count($names) - 1];
    }
}
