<?php

/** @bidirekt schema */
final class Owner
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field type=Owner[] */
    public array $owners;

    /** @bidirekt field type=map<Owner> */
    public array $byNickname;

    /** @bidirekt field type=map<string,Owner> */
    public array $byName;
}
