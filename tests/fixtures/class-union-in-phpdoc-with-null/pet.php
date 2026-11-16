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
    /** @var array<string, CardPayment|PixPayment|null> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public array $byMethod;

    /** @var list<list<CardPayment|PixPayment>> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public array $history;
}
