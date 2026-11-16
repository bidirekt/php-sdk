<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [])]
final class Pet
{
    public string $name;
}

final class HealthController
{
    #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])]
    #[BidirektSchema(name: 'Health', fields: [])]
    public function health(): void {}

    #[BidirektProvider(method: 'GET', path: '/ready', responses: [200 => 'Ready'])]
    #[BidirektSchema(name: 'Ready')]
    public function ready(): void {}
}
