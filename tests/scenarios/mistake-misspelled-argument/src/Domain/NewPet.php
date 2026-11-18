<?php

declare(strict_types=1);

namespace App\Domain;

/** @bidirekt schema */
final class NewPet
{
    /** @bidirekt field */
    public string $name;

    /** @bidirekt field type=float */
    public float $weight;

    /** @bidirekt field */
    public int $ownerId;

    /** @bidirekt field optional=true */
    public ?int $categoryId = null;

    /**
     * @bidirekt field optional=true
     * @var list<string>
     */
    public array $tags = [];

    /**
     * @param array<mixed> $body
     */
    public static function fromBody(array $body): ?self
    {
        if (!is_string($body['name'] ?? null) || !is_int($body['ownerId'] ?? null)) {
            return null;
        }
        $newPet = new self();
        $newPet->name = $body['name'];
        $newPet->weight = (float) ($body['weight'] ?? 0);
        $newPet->ownerId = $body['ownerId'];
        $newPet->categoryId = is_int($body['categoryId'] ?? null) ? $body['categoryId'] : null;
        $newPet->tags = array_values(array_filter((array) ($body['tags'] ?? []), 'is_string'));

        return $newPet;
    }
}
