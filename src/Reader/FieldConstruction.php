<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * A `new BidirektField(…)` written in an attribute's `fields:`, with its arguments read like an attribute's.
 */
final class FieldConstruction
{
    public function __construct(
        public AttributeArguments $arguments,
    ) {}
}
