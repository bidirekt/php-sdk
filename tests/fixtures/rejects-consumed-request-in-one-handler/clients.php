<?php

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class NewCharge
{
    #[BidirektField]
    public float $amount;
}

final class CheckoutClient
{
    #[BidirektConsumer(provider: 'payments_api', method: 'POST', path: '/charges', request: NewCharge::class, responses: [201 => null])]
    public function charge(): void {}
}

final class RetryClient
{
    #[BidirektConsumer(provider: 'payments_api', method: 'POST', path: '/charges', responses: [201 => null])]
    public function retry(): void {}
}
