<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /**
     * @param list<string> $tags
     * @param array<string, int> $scores
     */
    public function __construct(
        #[BidirektField] public array $tags,
        #[BidirektField] public array $scores,
        /** @var list<float> */
        #[BidirektField] public array $weights,
        #[BidirektField]
        /** @var list<bool> */
        public array $checks,
    ) {}
}
