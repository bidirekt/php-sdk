<?php

use Symfony\Component\Routing\Attribute\Route;

$app = new Router();
$app
    /** @bidirekt provides GET /pets */
    ->get('/pets', 'listPets');

$router = new Router();
$router
    ->route('/pets/{id}')
    /** @bidirekt provides GET /pets/* */
    ->get('/pets/{id}', 'findPet')
    /** @bidirekt provides DELETE /pets/* */
    ->delete('deletePet');

$router->get(/** @bidirekt provides GET /owners/* */ '/owners/{id}', 'findPet');

$routes = [
    /** @bidirekt provides GET /shelters */
    '/shelters' => 'listShelters',
];

final class PetsController
{
    #[Route('/toys/{id}')]
    /** @bidirekt provides GET /toys/* */
    public function findOne(): void {}

    /**
     * @bidirekt provides GET /toys
     * @bidirekt response 200
     */
    public function findAll(): void {}

    /** @bidirekt provides DELETE /toys/* */
}
