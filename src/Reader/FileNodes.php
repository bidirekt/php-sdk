<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
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
     * @param array<int, ClassLike> $enclosingClasses node id => the class-like a member or a class constant fetch is written in
     * @param array<int, Node> $attributeElements attribute id => the element its group is on
     */
    public function __construct(
        public array $classLikes = [],
        public array $functionLikes = [],
        public array $attributes = [],
        public array $members = [],
        public array $commented = [],
        public array $enclosingClasses = [],
        public array $attributeElements = [],
    ) {}

    /**
     * @param Stmt\Property|Param|ClassConstFetch $node
     */
    public function enclosingClassOf(Node $node): ?ClassLike
    {
        return $this->enclosingClasses[spl_object_id($node)] ?? null;
    }

    public function elementOf(Attribute $attribute): ?Node
    {
        return $this->attributeElements[spl_object_id($attribute)] ?? null;
    }
}
