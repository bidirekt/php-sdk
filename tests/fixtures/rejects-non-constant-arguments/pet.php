<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(description: new Description('Shown on the card'))]
    public string $name;

    #[BidirektField(description: PHP_EOL)]
    public string $nickname;

    #[BidirektField(description: 'Shown ' . 'twice')]
    public string $title;

    #[BidirektField(name: self::MISSING)]
    public string $owner;

    #[BidirektField(name: strtoupper('breed'))]
    public string $breed;
}

final class Description
{
    public function __construct(public string $text)
    {
    }
}
