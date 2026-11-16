<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * An array as written: every entry kept in order, the repeated keys and the entries without a key
 * included, which PHP would merge or number.
 */
final class ConstantArray
{
    /**
     * @param list<ConstantArrayItem> $items
     */
    public function __construct(
        public array $items,
    ) {}
}
