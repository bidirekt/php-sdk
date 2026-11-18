<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

final class ConstantArrayItem
{
    /**
     * @param ConstantValue|null $key null when the entry is written without a key
     */
    public function __construct(
        public ?ConstantValue $key,
        public ConstantValue $value,
    ) {}
}
