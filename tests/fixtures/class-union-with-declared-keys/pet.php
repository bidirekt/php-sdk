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
final class Order
{
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public CardPayment|PixPayment $payment;

    #[BidirektField(union: ['pix' => PixPayment::class, 'card' => CardPayment::class], optional: true)]
    public CardPayment|PixPayment|null $refund;
}
