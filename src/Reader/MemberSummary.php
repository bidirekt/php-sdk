<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * A property or a promoted constructor parameter, which a schema reads as a field.
 */
final class MemberSummary
{
    /**
     * @param bool $mentionsSpecialClass whether its type, docblocks or attributes write `self`, `static` or
     *                                   `parent`, which name another class for each schema that reads it
     */
    public function __construct(
        public string $name,
        public SourceLocation $location,
        public FieldSource $source,
        public bool $mentionsSpecialClass,
    ) {}
}
