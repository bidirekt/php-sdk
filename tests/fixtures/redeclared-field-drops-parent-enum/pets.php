<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum LegacyStatus: string
{
    case On = 'on';
}

abstract class Base
{
    /** @var LegacyStatus */
    #[BidirektField] public $status;
}

#[BidirektSchema]
final class Pet extends Base
{
    #[BidirektField(type: 'string')] public $status;
}
