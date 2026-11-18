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

#[BidirektSchema]
final class Receipt
{
    #[BidirektField]
    public string $id;
}

#[BidirektSchema]
final class AuditEntry
{
    #[BidirektField]
    public string $message;
}

final class PaymentsClient
{
    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        request: NewCharge::class,
        responses: [201 => Charge::class, 402 => null],
    )]
    public function charge(): void {}

    #[BidirektConsumer(provider: 'payments_api', method: 'GET', path: '/charges', responses: [200 => [Charge::class]])]
    public function list(): void {}

    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'GET',
        path: '/charges/recent',
        responses: [200 => ['list' => Charge::class, 'name' => 'ChargeCollection']],
    )]
    public function recent(): void {}

    #[BidirektConsumer(provider: 'payments_api', method: 'GET', path: '/charges/*/receipt', responses: [200 => Receipt::class])]
    public function receipt(): void {}

    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'PATCH',
        path: '/charges/*',
        request: null,
        responses: [200 => Charge::class, 404 => null],
    )]
    public function touch(): void {}
}

final class AuditClient
{
    #[BidirektConsumer(
        provider: 'audit_api',
        method: 'PUT',
        path: '/entries/*',
        request: AuditEntry::class,
        responses: [204 => null],
    )]
    public function record(): void {}
}
