<?php

/** @bidirekt schema name=PetResponse */
class Pet
{
    /** @bidirekt field type=integer */
    public $id;

    /**
     * @bidirekt field
     * @var string
     */
    public $name;
}

/** @bidirekt schema */
class NewCharge
{
    /** @bidirekt field type=integer */
    public $amount;
}

/**
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200 PetResponse
 * @bidirekt response 404
 */
function findPet()
{
}

/**
 * @bidirekt provides GET /pets
 * @bidirekt response 200 PetResponse[]
 */
$listPets = fn () => [];

/**
 * @bidirekt consumes payments_api POST /charges
 * @bidirekt request NewCharge
 * @bidirekt response 201
 */
$createCharge = fn ($body) => $body;
