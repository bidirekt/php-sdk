<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'Health', fields: [
    new BidirektField('status', type: 'string', description: 'Up ' . 'or down'),
    new BidirektField('uptime', type: 'integer'),
])]
#[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])]
function health(): array { return []; }

#[BidirektProvider(method: 'GET', path: '/ready', responses: [200 => 'Health'])]
function ready(): array { return []; }
