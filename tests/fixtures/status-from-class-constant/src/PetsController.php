<?php

namespace App;

use Bidirekt\PhpSdk\BidirektProvider;
use Fig\Http\Message\StatusCodeInterface;

final class PetsController implements StatusCodeInterface
{
    private const STATUS_GONE = 410;

    #[BidirektProvider(
        method: 'GET',
        path: '/pets/*',
        responses: [
            StatusCodeInterface::STATUS_OK => Pet::class,
            self::STATUS_NOT_FOUND => null,
            self::STATUS_GONE => null,
        ],
    )]
    public function find(): void {}
}
