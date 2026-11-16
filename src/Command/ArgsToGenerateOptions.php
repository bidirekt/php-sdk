<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

final class ArgsToGenerateOptions
{
    private const VALUE_OPTIONS = ['project', 'out'];

    /**
     * @param list<string> $args
     * @return GenerateOptions|null null when the args are not `generate [--check] [--project <dir>] [--out <file>]`
     */
    public static function argsToGenerateOptions(array $args): ?GenerateOptions
    {
        $options = new GenerateOptions();
        $positionals = [];
        for ($index = 0; $index < count($args); $index++) {
            $arg = $args[$index];
            if ($arg === '--') {
                array_push($positionals, ...array_slice($args, $index + 1));

                break;
            }
            if (!str_starts_with($arg, '--')) {
                $positionals[] = $arg;

                continue;
            }
            [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
            if ($name === 'check' && $value === null) {
                $options->check = true;

                continue;
            }
            if (!in_array($name, self::VALUE_OPTIONS, true)) {
                return null;
            }
            if ($value === null) {
                $index++;
                $value = $args[$index] ?? null;
            }
            if ($value === null) {
                return null;
            }
            if ($name === 'project') {
                $options->project = $value;

                continue;
            }
            $options->out = $value;
        }
        if ($positionals !== ['generate']) {
            return null;
        }

        return $options;
    }
}
