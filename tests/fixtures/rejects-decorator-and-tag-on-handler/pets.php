<?php

use Bidirekt\PhpSdk\BidirektProvider;

final class PetsController
{
    /**
     * @bidirekt provides GET /pets
     * @bidirekt response 200
     */
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [204 => null])]
    public function list(): void
    {
    }
}
