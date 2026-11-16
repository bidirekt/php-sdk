<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

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
     */
    public function __construct(
        public TypeNode $node,
        private array $tokens,
        private NameScope $scope,
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
        return $this->scope->classNameToFullyQualified($written);
    }

    /**
     * The type with each name resolved, so an alias and the fully-qualified name it stands for read the same.
     */
    public function typeToResolvedText(): string
    {
        $resolver = new class ($this->scope) extends AbstractNodeVisitor {
            public function __construct(
                private NameScope $scope,
            ) {}

            public function enterNode(Node $node): ?Node
            {
                if ($node instanceof IdentifierTypeNode) {
                    $node->name = $this->scope->classNameToFullyQualified($node->name);
                }

                return null;
            }
        };
        [$resolved] = (new NodeTraverser([new CloningVisitor(), $resolver]))->traverse([$this->node]);

        return (string) $resolved;
    }
}
