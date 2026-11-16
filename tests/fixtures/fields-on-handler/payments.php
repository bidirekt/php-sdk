<?php

namespace App\Http;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class CardPayment
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField]
    public int $id;

    #[BidirektField(type: 'Receipt')]
    public mixed $receipt;
}

final class OrdersController
{
    #[BidirektProvider(method: 'POST', path: '/orders', request: 'NewOrder', responses: [201 => Order::class])]
    #[BidirektSchema(name: 'NewOrder', fields: [
        new BidirektField('petId', type: 'integer'),
        new BidirektField('quantity', type: 'integer', optional: true),
        new BidirektField('payment', union: ['card' => CardPayment::class, 'pix' => 'Pix']),
    ])]
    #[BidirektSchema(name: 'Pix', fields: [
        new BidirektField('key', type: 'string'),
    ])]
    public function create(): void {}

    #[BidirektProvider(method: 'GET', path: '/orders/*/receipt', responses: [200 => 'Receipt'])]
    public function receipt(): void {}
}

final class PaymentsClient
{
    #[BidirektConsumer(provider: 'payments_api', method: 'POST', path: '/receipts', request: 'NewOrder', responses: [201 => 'Receipt'])]
    #[BidirektSchema(name: 'Receipt', fields: [
        new BidirektField('total', type: 'number'),
        new BidirektField('paidAt', type: 'string', nullable: true, description: 'Empty until paid'),
    ])]
    public function issue(): void {}
}
