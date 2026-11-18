<?php

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Charge
{
    #[BidirektField]
    public string $id;
}

#[BidirektSchema]
final class NewCharge
{
    #[BidirektField]
    public float $amount;
}

final class CheckoutClient
{
    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        request: NewCharge::class,
        responses: [201 => Charge::class, 402 => null],
    )]
    public function charge(): void {}
}

final class RetryClient
{
    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        request: NewCharge::class,
        responses: [201 => Charge::class, 409 => null],
    )]
    public function retry(): void {}
}
