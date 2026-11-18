<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

trait FindsPets
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class])]
    public function find(): void {}
}

abstract class ListController
{
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => [Pet::class]])]
    public function list(): void {}
}

final class DogsController extends ListController
{
    use FindsPets;
}

final class CatsController extends ListController
{
    use FindsPets;
}
