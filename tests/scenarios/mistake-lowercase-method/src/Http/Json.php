<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ResponseInterface;

final class Json
{
    public static function respond(ResponseInterface $response, mixed $body, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($body, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));

        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
