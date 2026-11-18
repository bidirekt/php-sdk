<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Storefront\Petstore\PetstoreClient;
use Storefront\ShopWindow;

require __DIR__ . '/../vendor/autoload.php';

$petstore = new PetstoreClient(new Client(['base_uri' => getenv('PETSTORE_API_URL') ?: 'http://petstore-api']));

echo (new ShopWindow($petstore))->render();
