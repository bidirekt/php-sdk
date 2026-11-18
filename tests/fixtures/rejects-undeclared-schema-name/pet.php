<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'Missing')]
    public array $owner;

    #[BidirektField(type: 'array', items: 'Owner')]
    public array $owners;
}
