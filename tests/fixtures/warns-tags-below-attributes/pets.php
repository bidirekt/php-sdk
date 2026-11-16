<?php

use Doctrine\ORM\Mapping as ORM;

/** @bidirekt schema */
#[ORM\Entity]
final class Pet
{
    /** @bidirekt field */
    #[ORM\Column]
    public string $name;

    #[ORM\Column]
    /** @bidirekt field */
    public string $breed;
}

#[ORM\Entity]
/** @bidirekt schema */
final class Owner
{
    /** @bidirekt field */
    public string $name;
}
