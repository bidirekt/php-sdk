<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\NameContext;
use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\Node;
use PHPStan\PhpDocParser\Ast\NodeTraverser;
use PHPStan\PhpDocParser\Ast\NodeVisitor\CloningVisitor;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;

/**
 * A type as written in the code or in a docblock, parsed by the phpdoc-parser, with what it takes to
 * quote any part of it and to resolve the class names it writes.
 */
final class WrittenType
{
    /**
     * @param list<array{string, int, int}> $tokens the lexer tokens the node's index attributes point at
     * @param bool $native whether it is a native type, where only PHP's own keywords are not class names
     */
    public function __construct(
        public TypeNode $node,
        private array $tokens,
        private NameContext $names,
        public bool $native = false,
    ) {}

    /**
     * The part of the type as written, whitespace runs shown as one space.
     */
    public function textOf(TypeNode $node): string
    {
        $start = $node->getAttribute(Attribute::START_INDEX);
        $end = $node->getAttribute(Attribute::END_INDEX);
        if (!is_int($start) || !is_int($end)) {
            return (string) $node;
        }
        $text = '';
        for ($index = $start; $index <= $end && isset($this->tokens[$index]); $index++) {
            $text .= $this->tokens[$index][Lexer::VALUE_OFFSET];
        }

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    public function classNameToFullyQualified(string $written): string
    {
        return NameContextVisitor::classNameToFullyQualified($this->names, $written);
    }

    /**
     * The type with each name resolved, so an alias and the fully-qualified name it stands for read the same.
     */
    public function typeToResolvedText(): string
    {
        $resolver = new class ($this->names) extends AbstractNodeVisitor {
            public function __construct(
                private NameContext $names,
            ) {}

            public function enterNode(Node $node): ?Node
            {
                if ($node instanceof IdentifierTypeNode) {
                    $node->name = NameContextVisitor::classNameToFullyQualified($this->names, $node->name);
                }

                return null;
            }
        };
        [$resolved] = (new NodeTraverser([new CloningVisitor(), $resolver]))->traverse([$this->node]);

        return (string) $resolved;
    }
}
