<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Card
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema]
final class Pix
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class Boleto
{
    #[BidirektField]
    public string $barcode;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField(union: ['card' => Card::class, 'pix' => Pix::class, 'boleto' => Boleto::class])]
    public Card|Pix $payment;

    #[BidirektField(union: ['card' => Card::class])]
    public Card|Pix|null $refund;

    #[BidirektField(union: ['card' => Card::class, 'credit' => Card::class, 'pix' => Pix::class])]
    public Card|Pix $deposit;

    /** @var list<Card|Pix> */
    #[BidirektField(union: ['card' => Card::class])]
    public array $attempts;
}
