<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;

final class ArgumentsToFieldOptions
{
    /**
     * @return FieldOptions|list<Diagnostic>
     */
    public static function argumentsToFieldOptions(AttributeArguments $arguments): FieldOptions|array
    {
        $name = $arguments->nameArgument('name');
        $description = $arguments->stringArgument('description');
        $optional = $arguments->boolArgument('optional');
        $nullable = $arguments->boolArgument('nullable');
        if ($name instanceof Diagnostic || $description instanceof Diagnostic || $optional instanceof Diagnostic || $nullable instanceof Diagnostic) {
            return array_values(array_filter(
                [$name, $description, $optional, $nullable],
                static fn(mixed $value): bool => $value instanceof Diagnostic,
            ));
        }

        return new FieldOptions($name, $description, $optional, $nullable, $arguments);
    }
}
