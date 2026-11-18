<?php

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'Health')]
final class HealthReport
{
    #[BidirektField]
    public string $status;
}

final class HealthController
{
    #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health', 503 => null])]
    public function health(): void {}

    #[BidirektProvider(method: 'GET', path: '/health/checks', responses: [200 => ['Health']])]
    public function checks(): void {}

    #[BidirektProvider(method: 'GET', path: '/health/history', responses: [200 => ['list' => 'Health', 'name' => 'HealthHistory']])]
    public function history(): void {}
}

final class MonitorClient
{
    #[BidirektConsumer(provider: 'monitor_api', method: 'POST', path: '/reports', request: 'Health', responses: [202 => null])]
    public function report(): void {}

    #[BidirektConsumer(provider: 'monitor_api', method: 'POST', path: '/reports/batch', request: ['Health'], responses: [202 => ['list' => HealthReport::class]])]
    public function reportBatch(): void {}
}
