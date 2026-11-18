<?php

declare(strict_types=1);

namespace Storefront\Petstore;

use Bidirekt\PhpSdk\BidirektConsumer;
use GuzzleHttp\ClientInterface;

final class PetstoreClient
{
    public function __construct(private ClientInterface $http)
    {
    }

    /**
     * @bidirekt consumes petstore_api GET /pets
     * @bidirekt response 200 Pet[]
     *
     * @return list<Pet>
     */
    public function pets(): array
    {
        $response = $this->http->request('GET', '/pets');
        $pets = [];
        foreach ($this->decode((string) $response->getBody()) as $body) {
            $pets[] = Pet::fromBody($body);
        }

        return $pets;
    }

    #[BidirektConsumer(provider: 'petstore_api', method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    public function pet(int $id): ?Pet
    {
        $response = $this->http->request('GET', '/pets/' . $id, ['http_errors' => false]);
        if ($response->getStatusCode() === 404) {
            return null;
        }

        return Pet::fromBody($this->decode((string) $response->getBody()));
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $json): array
    {
        return (array) json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }
}
