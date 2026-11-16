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
    #[BidirektField(union: [CardPayment::class])]
    public CardPayment $payment;

    #[BidirektField(union: ['card' => CardPayment::class, 'card' => 'CardPayment'])]
    public CardPayment $refund;

    #[BidirektField(union: ['card' => 42])]
    public CardPayment $deposit;

    #[BidirektField(union: 'CardPayment')]
    public CardPayment $credit;
}
