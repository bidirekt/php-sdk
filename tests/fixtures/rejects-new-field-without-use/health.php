<?php

namespace App;

use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'Health', fields: [
    new BidirektField('status', type: 'string'),
])]
#[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])]
function health(): array { return []; }
