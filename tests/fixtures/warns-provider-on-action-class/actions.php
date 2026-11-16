<?php

namespace App\Action;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => null])]
final class ListPetsAction
{
    public function __invoke(): void {}
}

#[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => null])]
final class ShowPetAction
{
    #[BidirektConsumer(provider: 'stock', method: 'GET', path: '/stock', responses: [200 => null])]
    public const STOCK = 'stock';

    public function __construct(
        #[BidirektProvider(method: 'GET', path: '/owners', responses: [200 => null])]
        private string $owner,
        #[BidirektSchema(name: 'Shown')]
        public string $shown,
    ) {}

    #[BidirektSchema(name: 'Show')]
    public string $show;

    public function __invoke(): void {}
}

#[BidirektField]
enum PetStatus: string
{
    #[BidirektProvider(method: 'GET', path: '/statuses', responses: [200 => null])]
    case Available = 'available';
}

#[BidirektProvider(method: 'DELETE', path: '/pets/*', responses: [204 => null])]
function deletePet(): void {}
