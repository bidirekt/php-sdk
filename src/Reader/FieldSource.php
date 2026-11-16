<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\NameContext;
use PhpParser\Node;

/**
 * Where a field's type is written: its native type and the docblocks that may describe it.
 */
final class FieldSource
{
    /**
     * @param string|null $varDoc the docblock of the property or of the promoted parameter, read for its @var
     * @param string|null $constructorDoc the constructor's docblock for a promoted parameter, read for its @param
     */
    public function __construct(
        public string $member,
        public ?Node $nativeType,
        public ?string $varDoc,
        public ?string $constructorDoc,
        public NameContext $names,
    ) {}
}
