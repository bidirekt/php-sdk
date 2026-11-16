<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * Declared with @bidirekt/php-sdk attributes.
 * @bidirekt/php-sdk reads them; install it with
 * `composer require --dev bidirekt/php-sdk`.
 * @see @bidirekt/php-sdk
 */
#[BidirektSchema]
final class Pet
{
    /** @see @bidirekt/php-sdk */
    #[BidirektField]
    public string $name;
}
