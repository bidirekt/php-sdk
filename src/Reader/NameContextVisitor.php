<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\ErrorHandler\Collecting;
use PhpParser\NameContext;
use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Name\Relative;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;

/**
 * Resolves the class names the reader reads as PHP-Parser's NameResolver does, the written one kept in the
 * "originalName" attribute: the names of attributes, of `extends`, `implements` and trait `use`, and the class
 * of `X::NAME` and `new X()`, and names each class-like. The other names, which nothing reads, are left as
 * written. Keeps the namespace and `use` aliases in force on every node too, for the names written in
 * docblocks; its contexts are built from names of their own, not the tree's, so the summaries that keep one
 * outlive the tree.
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
        $this->resolveNames($node);

        return null;
    }

    private function resolveNames(Node $node): void
    {
        if ($node instanceof Attribute) {
            $node->name = $this->classNameToResolved($node->name);

            return;
        }
        if (($node instanceof ClassConstFetch || $node instanceof New_) && $node->class instanceof Name) {
            $node->class = $this->classNameToResolved($node->class);

            return;
        }
        if ($node instanceof TraitUse) {
            $node->traits = array_map(fn(Name $trait): Name => $this->classNameToResolved($trait), $node->traits);

            return;
        }
        if (!$node instanceof ClassLike) {
            return;
        }
        if ($node instanceof Class_ && $node->extends !== null) {
            $node->extends = $this->classNameToResolved($node->extends);
        }
        if ($node instanceof Class_ || $node instanceof Enum_) {
            $node->implements = array_map(fn(Name $interface): Name => $this->classNameToResolved($interface), $node->implements);
        }
        if ($node instanceof Interface_) {
            $node->extends = array_map(fn(Name $interface): Name => $this->classNameToResolved($interface), $node->extends);
        }
        $node->namespacedName = null;
        if ($node->name !== null) {
            $node->namespacedName = Name::concat($this->context->getNamespace(), (string) $node->name);
        }
    }

    private function classNameToResolved(Name $name): Name
    {
        $resolved = clone $name;
        $resolved->setAttribute('originalName', $name);

        return $this->context->getResolvedName($resolved, Use_::TYPE_NORMAL) ?? $resolved;
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
