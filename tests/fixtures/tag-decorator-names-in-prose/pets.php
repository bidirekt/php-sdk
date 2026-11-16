<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/**
 * The pet the API returns.
 * @BidirektSchema names it in the contract, and
 * @BidirektField marks each field the contract carries.
 */
#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;

    /**
     * @BidirektProvider and @BidirektConsumer go on handler methods.
     * Kept out of the contract: it has no @BidirektField.
     */
    public string $internalNotes;
}
