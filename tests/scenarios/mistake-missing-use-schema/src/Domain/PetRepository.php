<?php

declare(strict_types=1);

namespace App\Domain;

interface PetRepository
{
    /**
     * @return list<Pet>
     */
    public function all(): array;

    public function find(int $petId): ?Pet;

    public function add(NewPet $newPet): Pet;

    public function remove(int $petId): bool;
}
