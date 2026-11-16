<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

final class HealthController
{
    #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => null])]
    #[BidirektSchema(fields: [new BidirektField('status', type: 'string')])]
    public function health(): void {}
}

#[BidirektSchema(name: '', fields: [new BidirektField('ok', type: 'boolean')])]
function readiness(): void {}
