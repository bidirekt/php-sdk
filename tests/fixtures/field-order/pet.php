<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

trait Audited
{
    #[BidirektField]
    public string $updatedBy;
}

class Animal
{
    #[BidirektField]
    public int $id;

    #[BidirektField]
    public string $name;

    #[BidirektField]
    public bool $vaccinated;
}

#[BidirektSchema]
final class Dog extends Animal
{
    #[BidirektField]
    public string $breed;

    use Audited;

    #[BidirektField(description: 'The name on the collar')]
    public string $name;

    public function __construct(
        #[BidirektField] public string $owner,
    ) {
    }

    #[BidirektField]
    public string $color;
}
