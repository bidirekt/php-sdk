<?php

namespace App\Repository;

use App\Dto\CategoryResponse;
use App\Dto\CreatePetRequest;
use App\Dto\PetResponse;
use App\Dto\UpdatePetRequest;
use App\Enum\PetStatus;

final class PetRepository
{
    /** @var array<int, PetResponse> */
    private array $pets = [];

    /** @var array<int, CategoryResponse> */
    private array $categories;

    public function __construct()
    {
        $this->categories = [
            1 => new CategoryResponse(1, 'Dogs'),
            2 => new CategoryResponse(2, 'Cats'),
        ];
    }

    /**
     * @return list<CategoryResponse>
     */
    public function categories(): array
    {
        return array_values($this->categories);
    }

    /**
     * @return list<PetResponse>
     */
    public function page(int $page, int $size): array
    {
        return array_slice(array_values($this->pets), ($page - 1) * $size, $size);
    }

    public function count(): int
    {
        return count($this->pets);
    }

    public function find(int $id): ?PetResponse
    {
        return $this->pets[$id] ?? null;
    }

    public function add(CreatePetRequest $request): PetResponse
    {
        $id = count($this->pets) + 1;

        return $this->pets[$id] = new PetResponse(
            $id,
            new \DateTimeImmutable(),
            $request->name,
            $request->status,
            $this->categories[$request->categoryId ?? 0] ?? null,
            $request->photoUrls,
            $request->tags,
        );
    }

    public function update(PetResponse $pet, UpdatePetRequest $request): PetResponse
    {
        return $this->pets[$pet->id] = new PetResponse(
            $pet->id,
            $pet->createdAt,
            $request->name ?? $pet->name,
            $request->status ?? $pet->status,
            $pet->category,
            $pet->photoUrls,
            $pet->tags,
        );
    }

    public function remove(int $id): bool
    {
        if (!isset($this->pets[$id])) {
            return false;
        }
        unset($this->pets[$id]);

        return true;
    }
}
