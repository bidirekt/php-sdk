<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;

final class NodeToEnclosingClass
{
    public static function nodeToEnclosingClass(Node $node): ?ClassLike
    {
        for ($current = $node->getAttribute('parent'); $current instanceof Node; $current = $current->getAttribute('parent')) {
            if ($current instanceof ClassLike) {
                return $current;
            }
        }

        return null;
    }
}
