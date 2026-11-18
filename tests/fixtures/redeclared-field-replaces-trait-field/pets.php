<?php

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public string $name = '';
}

trait HasData
{
    /** @bidirekt field */
    public mixed $data = null;
}

/** @bidirekt schema */
final class PetEnvelope
{
    use HasData;

    /** @bidirekt field type=Pet */
    public mixed $data = null;
}
