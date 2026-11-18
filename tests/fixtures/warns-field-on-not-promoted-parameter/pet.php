<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;

    public function __construct(
        #[BidirektField] string $nickname,
    ) {
        $this->name = $nickname;
    }

    public function rename(#[BidirektField] string $name): void
    {
        $this->name = $name;
    }
}
