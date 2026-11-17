<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects the file's nodes into `FileNodes`. Runs after the NameResolver in the same traverser, so the names
 * it collects are resolved.
 *
 * The body of a method, function or closure is dropped before it is walked when its code holds nothing the
 * reader reads: no attribute (`#[`), no "bidirekt" tag and no class-like declaration. Most code is such
 * bodies, and the reader reads none of it.
 */
final class FileNodesVisitor extends NodeVisitorAbstract
{
    public FileNodes $nodes;

    /** @var list<Node> the nodes being walked, the outermost first */
    private array $path = [];

    /** @var list<ClassLike> the class-likes being walked, the outermost first */
    private array $classes = [];

    public function __construct(
        private string $code,
    ) {
        $this->nodes = new FileNodes();
    }

    public function beforeTraverse(array $nodes)
    {
        $this->nodes = new FileNodes();
        $this->path = [];
        $this->classes = [];

        return null;
    }

    public function enterNode(Node $node)
    {
        if ($node->getComments() !== []) {
            $this->nodes->commented[] = $node;
        }
        if (($node instanceof Stmt\Property || $node instanceof Param || $node instanceof ClassConstFetch) && $this->classes !== []) {
            $this->nodes->enclosingClasses[spl_object_id($node)] = $this->classes[count($this->classes) - 1];
        }
        if ($node instanceof ClassLike) {
            $this->nodes->classLikes[] = $node;
            $this->classes[] = $node;
        }
        if ($node instanceof FunctionLike) {
            $this->nodes->functionLikes[] = $node;
        }
        if (($node instanceof ClassMethod || $node instanceof Function_ || $node instanceof Closure) && $node->stmts !== null && $node->stmts !== [] && !$this->mayHoldWhatIsRead($node)) {
            $node->stmts = [];
        }
        if ($node instanceof Attribute) {
            $this->nodes->attributes[] = $node;
            $this->nodes->attributeElements[spl_object_id($node)] = $this->path[count($this->path) - 2];
        }
        if ($node instanceof Stmt\Property || $node instanceof Param) {
            $this->nodes->members[] = $node;
        }

        $this->path[] = $node;

        return null;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->path);
        if ($node instanceof ClassLike) {
            array_pop($this->classes);
        }

        return null;
    }

    /**
     * Whether the function's code, after its own attributes, has an attribute, a "bidirekt" tag or a class-like
     * declaration: its parameters' and its body's.
     */
    private function mayHoldWhatIsRead(ClassMethod|Function_|Closure $function): bool
    {
        $start = $function->getStartFilePos();
        if ($function->attrGroups !== []) {
            $start = $function->attrGroups[count($function->attrGroups) - 1]->getEndFilePos() + 1;
        }
        $code = substr($this->code, $start, $function->getEndFilePos() - $start + 1);

        return str_contains($code, '#[') || stripos($code, 'bidirekt') !== false || CodeToDeclaredClasses::codeToCandidateNames($code) !== [];
    }
}
