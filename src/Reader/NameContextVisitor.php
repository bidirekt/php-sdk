<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;

/**
 * Keeps the NameResolver's context (namespace and `use` aliases) on every node, because the NameResolver
 * does not resolve the names written in docblocks. Runs after the NameResolver in the same traverser.
 */
final class NameContextVisitor extends NodeVisitorAbstract
{
    public const ATTRIBUTE = 'bidirektNameContext';

    private NameContext $context;

    public function __construct(
        private NameResolver $resolver,
    ) {
        $this->context = clone $resolver->getNameContext();
    }

    public static function nodeToNameContext(Node $node): NameContext
    {
        $context = $node->getAttribute(self::ATTRIBUTE);
        if ($context instanceof NameContext) {
            return $context;
        }
        $global = new NameContext(new Throwing());
        $global->startNamespace();

        return $global;
    }

    /**
     * A class name as written in a docblock, resolved the way PHP resolves it in code.
     */
    public static function classNameToFullyQualified(NameContext $context, string $written): string
    {
        $name = new Name($written);
        if (str_starts_with($written, '\\')) {
            $name = new FullyQualified(substr($written, 1));
        }
        if (stripos($written, 'namespace\\') === 0) {
            $name = new Relative(substr($written, strlen('namespace\\')));
        }

        return $context->getResolvedClassName($name)->toString();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->context = clone $this->resolver->getNameContext();

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_ || $node instanceof Use_ || $node instanceof GroupUse) {
            $this->context = clone $this->resolver->getNameContext();
        }
        $node->setAttribute(self::ATTRIBUTE, $this->context);

        return null;
    }
}
