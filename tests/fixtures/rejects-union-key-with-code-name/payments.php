<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Card
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema(name: 'Pix')]
final class PixPayment
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField(union: ['card' => 'Card', 'pix' => 'PixPayment'])]
    public Card|PixPayment $payment;
}

#[BidirektSchema]
final class Refund
{
    #[BidirektField(union: ['card' => Card::class, 'pix' => 'Boleto'])]
    public Card|PixPayment $payment;
}
