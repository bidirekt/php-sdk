<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField] public string $name = '';
}

abstract class Page
{
    #[BidirektField] public int $total = 0;

    #[BidirektField] public array $items = [];
}

#[BidirektSchema]
final class PetPage extends Page
{
    /** @var list<Pet> */
    #[BidirektField] public array $items = [];
}
