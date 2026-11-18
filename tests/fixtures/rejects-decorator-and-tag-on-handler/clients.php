<?php

use Bidirekt\PhpSdk\BidirektConsumer;

final class PaymentsClient
{
    /**
     * @bidirekt consumes payments_api POST /charges
     * @bidirekt response 402
     */
    #[BidirektConsumer(provider: 'payments_api', method: 'POST', path: '/charges', responses: [201 => null])]
    public function charge(): void
    {
    }
}
