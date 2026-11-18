<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var integer */
    #[BidirektField]
    public $age;

    /** @var boolean|null */
    #[BidirektField]
    public $vaccinated;

    /** @var double */
    #[BidirektField]
    public $weight;

    /** @var list<integer> */
    #[BidirektField]
    public array $scores;

    /** @var integer|double */
    #[BidirektField]
    public $height;

    /** @var int|double */
    #[BidirektField]
    public $length;

    /** @var integer|float */
    #[BidirektField]
    public $width;
}
