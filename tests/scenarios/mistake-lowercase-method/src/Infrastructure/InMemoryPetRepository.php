<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Category;
use App\Domain\NewPet;
use App\Domain\Pet;
use App\Domain\PetRepository;

final class InMemoryPetRepository implements PetRepository
{
    /** @var array<int, Pet> */
    private array $pets;

    public function __construct()
    {
        $dogs = new Category(1, 'Dogs');
        $this->pets = [
            1 => new Pet(1, 'Rex', 'available', 12.5, 1, $dogs, ['friendly']),
            2 => new Pet(2, 'Mia', 'sold', 4.0, 2, null, []),
        ];
    }

    public function all(): array
    {
        return array_values($this->pets);
    }

    public function find(int $petId): ?Pet
    {
        return $this->pets[$petId] ?? null;
    }

    public function add(NewPet $newPet): Pet
    {
        $petId = max(array_keys($this->pets)) + 1;
        $category = null;
        if ($newPet->categoryId !== null) {
            $category = new Category($newPet->categoryId, 'Unknown');
        }
        $this->pets[$petId] = new Pet($petId, $newPet->name, 'available', $newPet->weight, $newPet->ownerId, $category, $newPet->tags);

        return $this->pets[$petId];
    }

    public function remove(int $petId): bool
    {
        if (!isset($this->pets[$petId])) {
            return false;
        }
        unset($this->pets[$petId]);

        return true;
    }
}
