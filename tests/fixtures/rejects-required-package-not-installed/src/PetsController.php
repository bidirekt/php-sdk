<?php

namespace App;

use Bidirekt\PhpSdk\BidirektProvider;
use Fig\Http\Message\StatusCodeInterface as Status;

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [Status::STATUS_OK => Pet::class])]
    public function find(): void {}
}
