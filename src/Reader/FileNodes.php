<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;

/**
 * The nodes of a file the reader looks for, each list in the order they are written, collected while the file
 * is parsed so no step searches the tree again.
 */
final class FileNodes
{
    /**
     * @param list<ClassLike> $classLikes anonymous classes included
     * @param list<FunctionLike> $functionLikes methods, functions, closures and arrow functions
     * @param list<Attribute> $attributes
     * @param list<Stmt\Property|Param> $members
     * @param list<Node> $commented the nodes with comments
     */
    public function __construct(
        public array $classLikes = [],
        public array $functionLikes = [],
        public array $attributes = [],
        public array $members = [],
        public array $commented = [],
    ) {}
}
