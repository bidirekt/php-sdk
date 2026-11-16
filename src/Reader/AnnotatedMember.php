<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;

/**
 * A property or promoted parameter with `#[BidirektField]` or `@bidirekt field`, not yet translated.
 */
final class AnnotatedMember
{
    /**
     * @param list<SdkAttribute> $attributes its #[BidirektField], one unless written twice
     * @param Stmt\Property|ClassMethod $declaration the property statement, or the constructor of a promoted parameter
     */
    public function __construct(
        public PropertyItem|Param $member,
        public array $attributes,
        public ?CommentTag $tag,
        public Stmt\Property|ClassMethod $declaration,
        public ClassDeclaration $owner,
        public ClassScope $scope,
    ) {}
}
