<?php

namespace App;

use Bidirekt\PhpSdk as Sdk;

#[Sdk\BidirektSchema]
final class Pet
{
    #[Sdk\BidirektField]
    public string $name;
}
