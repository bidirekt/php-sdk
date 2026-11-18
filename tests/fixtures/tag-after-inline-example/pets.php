<?php

/**
 * Finds a pet by its id.
 * @example findPet('42')
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200
 */
function findPet(string $id): string
{
    return $id;
}

/**
 * Lists the pets.
 * @example
 *   listPets()
 * @bidirekt provides GET /pets
 * @bidirekt response 200
 */
function listPets(): void {}
