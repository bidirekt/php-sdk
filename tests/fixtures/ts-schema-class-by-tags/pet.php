<?php

/** @bidirekt schema name=PetCard */
final class Pet
{
    /** @bidirekt field type=integer */
    public int $id;

    /** @bidirekt field name=birth_date optional=true */
    public ?string $birthDate;

    public string $internalNotes;
}
