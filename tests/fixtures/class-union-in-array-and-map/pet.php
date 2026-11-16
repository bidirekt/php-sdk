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
    /** @var list<CardPayment|PixPayment> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public array $payments;

    /** @var array<string, CardPayment|PixPayment> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public array $byMethod;

    /** @var list<CardPayment|PixPayment|null> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class], optional: true)]
    public ?array $attempts;
}
