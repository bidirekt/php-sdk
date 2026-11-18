<?php

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(nmae: 'Animal')]
final class Owner
{
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(nulable: false)]
    public ?string $name;
}

final class PaymentsClient
{
    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        requst: Pet::class,
        responses: [201 => null],
    )]
    public function charge(): void {}
}
