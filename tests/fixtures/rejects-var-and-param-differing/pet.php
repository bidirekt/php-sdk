<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        /** @var list<int> */
        #[BidirektField] public array $tags,
    ) {}
}
