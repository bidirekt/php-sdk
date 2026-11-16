<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

final class ConstantValue
{
    /**
     * @param int|float|string|bool|ClassReference|ConstantArray|FieldConstruction|null $value
     * @param SourceLocation $location where the expression is written
     * @param string|null $intLiteral an integer as written in the code, which tells 0200 from 200
     */
    public function __construct(
        public int|float|string|bool|ClassReference|ConstantArray|FieldConstruction|null $value,
        public SourceLocation $location,
        public ?string $intLiteral = null,
    ) {}

    /**
     * How messages show the value: `Pet::class`, `"Pet"`, `null`, `200`, `an array`…
     */
    public function valueToText(): string
    {
        if ($this->value instanceof ClassReference) {
            return SchemaNaming::classToShortName($this->value->class) . '::class';
        }
        if (is_string($this->value)) {
            return sprintf('"%s"', $this->value);
        }
        if ($this->value === null) {
            return 'null';
        }
        if (is_scalar($this->value)) {
            return var_export($this->value, true);
        }
        if ($this->value instanceof ConstantArray) {
            return 'an array';
        }

        return 'the value';
    }
}
