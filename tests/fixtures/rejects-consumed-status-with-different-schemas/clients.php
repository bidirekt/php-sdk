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
final class ChargeSummary
{
    #[BidirektField]
    public float $amount;
}

final class CheckoutClient
{
    #[BidirektConsumer(provider: 'payments_api', method: 'GET', path: '/charges/*', responses: [200 => Charge::class])]
    public function find(): void {}
}

final class ReportClient
{
    #[BidirektConsumer(provider: 'payments_api', method: 'GET', path: '/charges/*', responses: [200 => ChargeSummary::class])]
    public function summary(): void {}
}
