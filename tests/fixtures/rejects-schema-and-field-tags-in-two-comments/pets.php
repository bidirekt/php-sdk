<?php

/** @bidirekt schema name=Animal */
/** @bidirekt schema name=Pet */
final class Pet
{
    /** @bidirekt field name=nickname */
    /** @bidirekt field name=pet_name */
    public string $name;
}

/** @bidirekt schema */
/** Stays the same. */
/** @bidirekt schema name=Toy */
final class Toy
{
    /** @bidirekt field */
    public string $name;
}
