<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [
    new BidirektField('name'),
    new BidirektField('tag', type: 'string', union: ['card' => 'Card']),
    new BidirektField(type: 'string'),
    new BidirektField('', type: 'string'),
    'status' => new BidirektField('status', type: 'string'),
])]
final class Pet
{
}

final class OrdersController
{
    #[BidirektProvider(method: 'GET', path: '/orders/*', responses: [200 => 'Order'])]
    #[BidirektSchema(name: 'Order', fields: [
        new BidirektField('petId'),
        new BidirektField('payment', type: 'string', union: ['card' => 'Card']),
        'quantity',
    ])]
    public function find(): void {}
}
