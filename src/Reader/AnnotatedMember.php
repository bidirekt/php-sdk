<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * A property or promoted parameter with `#[BidirektField]` or `@bidirekt field`, not yet translated.
 */
final class AnnotatedMember
{
    /**
     * @param list<SdkAttribute> $attributes its #[BidirektField], one unless written twice
     */
    public function __construct(
        public MemberSummary $member,
        public array $attributes,
        public ?CommentTag $tag,
        public ClassScope $scope,
    ) {}
}
