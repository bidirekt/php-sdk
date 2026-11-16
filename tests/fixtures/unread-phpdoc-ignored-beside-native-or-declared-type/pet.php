<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var */
    #[BidirektField]
    public string $a;

    /** @var string The name shown on the card */
    #[BidirektField]
    public string $b;

    /** @var string|int Legacy */
    #[BidirektField]
    public string $c;

    /**
     * @var $d
     */
    #[BidirektField(type: 'string')]
    public $d;

    /**
     * @param $e the e
     */
    public function __construct(
        #[BidirektField] public int $e,
    ) {}
}
