<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

enum PetStatus: string
{
    case Available = 'available';
    case Sold = 1;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetStatus $status;
}

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets/status', responses: [200 => PetStatus::class])]
    public function status(): void {}
}
