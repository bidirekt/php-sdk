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
final class Order
{
    #[BidirektField(type: 'string', union: ['card' => CardPayment::class])]
    public CardPayment $payment;
}
