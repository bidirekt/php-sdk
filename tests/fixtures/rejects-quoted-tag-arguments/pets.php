<?php

/**
 * @bidirekt provides GET "/pets/by name"
 * @bidirekt response 200
 */
function findByName(): void
{
}

/** @bidirekt schema name="Pet Status" */
enum Status: string
{
    case Available = 'available';
}

/** @bidirekt schema name="Size" */
enum Size: string
{
    case Small = 'small';
}

/**
 * @bidirekt provides GET "/pets"
 * @bidirekt response 200
 */
function listPets(): void
{
}

/**
 * @bidirekt consumes "payments_api" GET /charges
 * @bidirekt response 200
 */
function listCharges(): void
{
}

/**
 * @bidirekt provides POST /pets
 * @bidirekt request "Status"
 * @bidirekt response 201 "Status"
 * @bidirekt response 200 Status[] as "Statuses"
 */
function createPet(): void
{
}
