<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'Health', fields: [
    new BidirektField('status', type: 'string'),
    new BidirektField('checks', type: 'array', items: 'HealthCheck'),
])]
function healthSchemas(): void {}

$check = #[BidirektSchema(name: 'HealthCheck', fields: [new BidirektField('ok', type: 'boolean')])] fn() => null;

$app->get('/health', #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])] function () {
    return ['status' => 'ok', 'checks' => []];
});
