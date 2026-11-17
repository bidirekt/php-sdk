<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Token;

final class ParsedFile
{
    /** @var array<int, SourceLocation> line => its location */
    private array $locations = [];

    /**
     * @param list<Node> $statements with the names the reader reads resolved, the original kept in the "originalName" attribute
     * @param list<Token> $tokens
     */
    public function __construct(
        public string $path,
        public string $code,
        public array $statements,
        public array $tokens,
        public FileNodes $nodes,
    ) {}

    /**
     * One location per line, which every element of the line shares.
     */
    public function lineToLocation(int $line): SourceLocation
    {
        return $this->locations[$line] ??= new SourceLocation($this->path, $line);
    }

    /**
     * The line of the element itself: a node's start line is the line of its first attribute.
     */
    public function locationOf(Node $node): SourceLocation
    {
        return $this->lineToLocation($this->lineOf($node));
    }

    /**
     * The node's code as written, whitespace runs shown as one space.
     */
    public function textOf(Node $node): string
    {
        $start = $node->getStartFilePos();
        if ($start < 0) {
            return '';
        }

        return (string) preg_replace('/\s+/', ' ', substr($this->code, $start, $node->getEndFilePos() - $start + 1));
    }

    /**
     * Names the node by where it is written in its file, for the summary and the tags of that file only.
     */
    public function nodeToKey(Node $node): string
    {
        return sprintf('%d:%d:%s', $node->getStartFilePos(), $node->getEndFilePos(), $node->getType());
    }

    private function lineOf(Node $node): int
    {
        if ($node instanceof Stmt\Property && $node->props !== []) {
            return $node->props[0]->getStartLine();
        }
        if ($node instanceof Param) {
            return $node->var->getStartLine();
        }
        if (($node instanceof ClassLike || $node instanceof ClassMethod || $node instanceof Function_ || $node instanceof EnumCase) && $node->name !== null) {
            return $node->name->getStartLine();
        }
        if (($node instanceof Closure || $node instanceof ArrowFunction) && $node->attrGroups !== []) {
            return $this->lineAfterAttributes($node->attrGroups[count($node->attrGroups) - 1]);
        }

        return $node->getStartLine();
    }

    private function lineAfterAttributes(Node\AttributeGroup $last): int
    {
        for ($position = $last->getEndTokenPos() + 1; isset($this->tokens[$position]); $position++) {
            if (!$this->tokens[$position]->isIgnorable()) {
                return $this->tokens[$position]->line;
            }
        }

        return $last->getEndLine();
    }
}
