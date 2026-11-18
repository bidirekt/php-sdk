<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

abstract class Base
{
    public const LABEL = 'name';
}

$pet = new #[BidirektSchema(name: 'Pet')] class extends Base {
    private const NICKNAME = 'nickname';

    #[BidirektField(name: parent::LABEL)]
    public string $name = '';

    #[BidirektField(name: self::NICKNAME)]
    public string $nick = '';
};

#[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => 'Pet'])]
function listPets(): void {}
