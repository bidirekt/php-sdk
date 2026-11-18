<?php

use Bidirekt\PhpSdk\BidirektProvider;

/**
 * @bidirekt provides GET /toys
 * @bidirekt response 200
 */
$app->get('/toys', #[BidirektProvider(method: 'GET', path: '/toys', responses: [204 => null])] function ($request, $response) {
    return $response;
});

/**
 * @bidirekt provides DELETE /toys/*
 * @bidirekt response 204
 */
$app->delete(
    '/toys/{id}',
    #[BidirektProvider(method: 'DELETE', path: '/toys/*', responses: [204 => null])]
    fn ($request, $response) => $response,
);
