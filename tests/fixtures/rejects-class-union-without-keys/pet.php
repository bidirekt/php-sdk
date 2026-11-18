<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class CardPayment
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema]
final class PixPayment
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public CardPayment|PixPayment $payment;
}
