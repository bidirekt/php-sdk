<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects the file's nodes into `FileNodes`. Runs after the NameResolver in the same traverser, so the names
 * it collects are resolved.
 */
final class FileNodesVisitor extends NodeVisitorAbstract
{
    public FileNodes $nodes;

    public function __construct()
    {
        $this->nodes = new FileNodes();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->nodes = new FileNodes();

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node->getComments() !== []) {
            $this->nodes->commented[] = $node;
        }
        if ($node instanceof ClassLike) {
            $this->nodes->classLikes[] = $node;
        }
        if ($node instanceof FunctionLike) {
            $this->nodes->functionLikes[] = $node;
        }
        if ($node instanceof Attribute) {
            $this->nodes->attributes[] = $node;
        }
        if ($node instanceof Stmt\Property || $node instanceof Param) {
            $this->nodes->members[] = $node;
        }

        return null;
    }
}
