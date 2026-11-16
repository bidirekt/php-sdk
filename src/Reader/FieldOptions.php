<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class FieldOptions
{
    /**
     * @param AttributeArguments $arguments every argument, for the ones that declare the type
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public ?bool $optional,
        public ?bool $nullable,
        public AttributeArguments $arguments,
    ) {}
}
