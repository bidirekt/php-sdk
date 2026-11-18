<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * A property statement, or a promoted constructor parameter: what its attributes and its docblock annotate.
 */
final class MemberDeclaration
{
    /**
     * @param string $key the node key its `@bidirekt field` tag is placed by
     * @param list<SdkAttribute> $attributes
     * @param list<MemberSummary> $members the statement's properties, or the parameter
     */
    public function __construct(
        public string $key,
        public array $attributes,
        public bool $static,
        public array $members,
    ) {}
}
