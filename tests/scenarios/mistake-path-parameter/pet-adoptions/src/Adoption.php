<?php

declare(strict_types=1);

namespace App;

/** @bidirekt schema */
final class Adoption
{
    public function __construct(
        /** @bidirekt field */
        public int $petId,
        /** @bidirekt field */
        public string $adopter,
    ) {
    }
}
