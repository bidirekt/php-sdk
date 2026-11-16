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

final class PaymentsClient
{
    #[BidirektConsumer(provider: 'payments_api', method: 'POST', path: '/charges', request: NewCharge::class, responses: [201 => Charge::class])]
    public function charge(NewCharge $charge): Charge
    {
        return new Charge();
    }
}

/**
 * @bidirekt consumes payments_api POST /charges
 * @bidirekt request NewCharge
 * @bidirekt response 201 Charge
 * @bidirekt response 402
 */
$retryCharge = static fn (NewCharge $charge): NewCharge => $charge;
