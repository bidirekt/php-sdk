<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

if (PHP_VERSION_ID >= 80100) {
    #[BidirektSchema]
    final class Money
    {
        #[BidirektField]
        public string $amount = '';
    }
} else {
    #[BidirektSchema]
    final class Money
    {
        #[BidirektField]
        public string $amount = '';
    }
}

#[BidirektProvider(method: 'GET', path: '/price', responses: [200 => Money::class])]
function price(): void {}
