<?php

declare(strict_types=1);

namespace Acme\Api\Pets;

use Acme\Shared\Clock;

final class PetRepository
{
    /** @var array<int, Pet> */
    private array $pets = [];

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
        $pet = new Pet();
        $pet->id = count($this->pets) + 1;
        $pet->createdAt = Clock::now();
        $pet->name = $newPet->name;
        $pet->nickname = $newPet->nickname;
        $pet->tags = $newPet->tags;
        $this->pets[$pet->id] = $pet;

        return $pet;
    }
}
