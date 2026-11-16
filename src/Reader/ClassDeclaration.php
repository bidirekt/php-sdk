<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node\Stmt\ClassLike;

final class ClassDeclaration
{
    public function __construct(
        public string $name,
        public ClassLike $node,
        public ParsedFile $file,
        public bool $inVendor,
    ) {}
}
