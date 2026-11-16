<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;
use LogicException;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\Token;

final class ParsedFile
{
    /** @var array<string, ClassDeclaration>|null lowercase name => its first declaration in the file */
    private ?array $declarations = null;

    /**
     * @param list<Node> $statements with names resolved, the original kept in the "originalName" attribute
     * @param list<Token> $tokens
     */
    public function __construct(
        public string $path,
        public array $statements,
        public array $tokens,
    ) {}

    public function lineToLocation(int $line): SourceLocation
    {
        return new SourceLocation($this->path, $line);
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
        $text = '';
        for ($position = $node->getStartTokenPos(); $position <= $node->getEndTokenPos() && isset($this->tokens[$position]); $position++) {
            $text .= $this->tokens[$position]->text;
        }

        return (string) preg_replace('/\s+/', ' ', $text);
    }

    /**
     * @return list<ClassDeclaration> the named class-likes, in the order they are written
     */
    public function classDeclarations(): array
    {
        $declarations = [];
        foreach ((new NodeFinder())->findInstanceOf($this->statements, ClassLike::class) as $node) {
            if ($node->namespacedName !== null) {
                $declarations[] = new ClassDeclaration($node->namespacedName->toString(), $node, $this);
            }
        }

        return $declarations;
    }

    public function classDeclarationNamed(string $lowercaseName): ?ClassDeclaration
    {
        if ($this->declarations === null) {
            $this->declarations = [];
            foreach ($this->classDeclarations() as $declaration) {
                $this->declarations[strtolower($declaration->name)] ??= $declaration;
            }
        }

        return $this->declarations[$lowercaseName] ?? null;
    }

    /**
     * Names the node by where it is written, so the node a later read of the file builds has the same key.
     */
    public function nodeToKey(Node $node): string
    {
        return sprintf('%s:%d:%d:%s', $this->path, $node->getStartFilePos(), $node->getEndFilePos(), $node::class);
    }

    /**
     * The class-like a read of the same code gave this key.
     */
    public function keyToClassLike(string $key): ClassLike
    {
        $found = (new NodeFinder())->findFirst($this->statements, fn(Node $node): bool => $node instanceof ClassLike && $this->nodeToKey($node) === $key);
        if (!$found instanceof ClassLike) {
            throw new LogicException(sprintf('no class-like in "%s" has the key "%s"', $this->path, $key));
        }

        return $found;
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
