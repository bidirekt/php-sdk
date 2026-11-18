<?php

declare(strict_types=1);

namespace App;

final class PetRepository
{
    /** @var array<int, Pet> */
    private array $pets;

    public function __construct()
    {
        $this->pets = [
            1 => new Pet(1, 'Rex', PetStatus::Available, '2021-03-14'),
            2 => new Pet(2, 'Mia', PetStatus::Sold, null),
        ];
    }

    /**
     * @return list<Pet>
     */
    public function all(): array
    {
        return array_values($this->pets);
    }

    public function find(int $id): ?Pet
    {
        return $this->pets[$id] ?? null;
    }

    public function add(NewPet $newPet): Pet
    {
        $id = count($this->pets) + 1;
        $this->pets[$id] = new Pet($id, $newPet->name, PetStatus::Available, $newPet->birthDate);

        return $this->pets[$id];
    }
}
