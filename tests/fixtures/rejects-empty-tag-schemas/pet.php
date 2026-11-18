<?php

/** @bidirekt schema */
final class Pet
{
    public string $name;
}

/** @bidirekt schema */
final class Owner
{
    public string $name;
}

/** @bidirekt schema */
final class Shelter
{
    /** @bidirekt field */
    public Pet $pet;

    /** @bidirekt field type=Owner */
    public object $owner;
}
