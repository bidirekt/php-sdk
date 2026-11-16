<?php

class Animal
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Pet extends Animal
{
    /** @bidirekt field */
    public string $breed;
}

final class Shelter
{
    /** @bidirekt field */
    public string $city;

    public function __construct(
        /** @bidirekt field */
        public string $country,
    ) {}
}

trait Located
{
    /** @bidirekt field */
    public string $street;
}

/** @bidirekt schema */
final class Owner
{
    /** @bidirekt field */
    public string $name;

    public function rename(
        /** @bidirekt field */
        string $name,
    ): void {
    }
}
