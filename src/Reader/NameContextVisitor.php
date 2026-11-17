<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * Keeps the namespace and `use` aliases in force on every node, because the NameResolver does not resolve the
 * names written in docblocks. Its contexts are built from names of their own, not the tree's, so the summaries
 * that keep one outlive the tree.
 */
final class NameContextVisitor extends NodeVisitorAbstract
{
    public const ATTRIBUTE = 'bidirektNameContext';

    private NameContext $context;

    public function __construct()
    {
        $this->context = self::namespaceToContext(null);
    }

    public static function nodeToNameContext(Node $node): NameContext
    {
        $context = $node->getAttribute(self::ATTRIBUTE);
        if ($context instanceof NameContext) {
            return $context;
        }

        return self::namespaceToContext(null);
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
        $this->context = self::namespaceToContext(null);

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Namespace_) {
            $this->context = self::namespaceToContext($node->name);
        }
        if ($node instanceof Use_ || $node instanceof GroupUse) {
            $this->context = clone $this->context;
            foreach ($node->uses as $use) {
                $name = new Name($use->name->toString());
                if ($node instanceof GroupUse) {
                    $name = new Name($node->prefix->toString() . '\\' . $use->name->toString());
                }
                $this->context->addAlias($name, (string) $use->getAlias(), $node->type | $use->type);
            }
        }
        $node->setAttribute(self::ATTRIBUTE, $this->context);

        return null;
    }

    private static function namespaceToContext(?Name $namespace): NameContext
    {
        $context = new NameContext(new Collecting());
        if ($namespace === null) {
            $context->startNamespace();

            return $context;
        }
        $context->startNamespace(new Name($namespace->toString()));

        return $context;
    }
}
