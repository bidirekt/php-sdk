<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * Keeps the class-name scope (namespace and `use` aliases) on every node, because the NameResolver
 * does not resolve the names written in docblocks.
 */
final class NameScopeVisitor extends NodeVisitorAbstract
{
    private NameScope $scope;

    public function __construct()
    {
        $this->scope = new NameScope('');
    }

    public function beforeTraverse(array $nodes)
    {
        $this->scope = new NameScope('');

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->scope = new NameScope($node->name?->toString() ?? '');
        }
        if ($node instanceof Use_) {
            foreach ($node->uses as $use) {
                if ($node->type === Use_::TYPE_NORMAL) {
                    $this->scope->addAlias($use->getAlias()->toString(), $use->name->toString());
                }
            }
        }
        if ($node instanceof GroupUse) {
            foreach ($node->uses as $use) {
                if ($node->type === Use_::TYPE_NORMAL || $use->type === Use_::TYPE_NORMAL) {
                    $this->scope->addAlias($use->getAlias()->toString(), $node->prefix->toString() . '\\' . $use->name->toString());
                }
            }
        }
        $node->setAttribute(NameScope::ATTRIBUTE, $this->scope);

        return null;
    }
}
