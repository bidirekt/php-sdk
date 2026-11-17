<?php

namespace App;

final class PetController
{
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => Pet::class])]
    public function list(): void {}

    #[BidirektConsumer(provider: 'owners', method: 'GET', path: '/owners/*', responses: [200 => Owner::class])]
    public function owner(): void {}
}
