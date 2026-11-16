<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * An attribute's arguments, positional ones named after the attribute's parameters, each evaluated.
 */
final class AttributeArguments
{
    /**
     * @param array<string, ConstantValue> $values parameter name => value, only the arguments written
     * @param SourceLocation $location the attribute's
     */
    public function __construct(
        public array $values,
        public SourceLocation $location,
    ) {}

    public function stringArgument(string $name): string|Diagnostic|null
    {
        $argument = $this->values[$name] ?? null;
        if ($argument === null) {
            return null;
        }
        if (!is_string($argument->value)) {
            return new Diagnostic(
                sprintf('"%s" must be a string: write a string or a class constant that holds one', $name),
                $argument->location,
            );
        }

        return $argument->value;
    }

    public function nameArgument(string $name): string|Diagnostic|null
    {
        $value = $this->stringArgument($name);
        if ($value === '') {
            return new Diagnostic(
                sprintf('"%s" is empty: write a name or leave "%s" out', $name, $name),
                $this->values[$name]->location,
            );
        }

        return $value;
    }

    public function boolArgument(string $name): bool|Diagnostic|null
    {
        $argument = $this->values[$name] ?? null;
        if ($argument === null) {
            return null;
        }
        if (!is_bool($argument->value)) {
            return new Diagnostic(sprintf('"%s" must be true or false: write true, false or a class constant that holds one', $name), $argument->location);
        }

        return $argument->value;
    }
}
