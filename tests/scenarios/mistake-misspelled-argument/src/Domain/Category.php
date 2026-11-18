<?php

declare(strict_types=1);

namespace App\Domain;

/** @bidirekt schema */
final class Category
{
    /** @bidirekt field */
    public int $id;

    /** @bidirekt field */
    public string $name;

    public function __construct(int $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }
}
