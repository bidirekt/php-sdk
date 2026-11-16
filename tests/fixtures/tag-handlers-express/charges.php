<?php

use GuzzleHttp\Client;

/**
 * @bidirekt consumes payments_api POST /charges
 * @bidirekt request NewCharge
 * @bidirekt response 201 Charge
 */
function createCharge(Client $http, NewCharge $charge): array
{
    return json_decode((string) $http->post('/charges', ['json' => $charge])->getBody(), true);
}
