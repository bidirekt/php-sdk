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
    #[BidirektField(type: 'array', items: ['union' => ['card' => CardPayment::class, 'pix' => 'PixPayment']])]
    public array $payments;

    #[BidirektField(type: 'map', values: ['union' => ['card' => CardPayment::class, 'pix' => PixPayment::class], 'nullable' => true])]
    public array $byMethod;

    #[BidirektField(type: 'map', values: ['type' => 'string', 'nullable' => true])]
    public array $notes;

    #[BidirektField(union: ['card' => 'CardPayment', 'pix' => PixPayment::class])]
    public $payment;
}
