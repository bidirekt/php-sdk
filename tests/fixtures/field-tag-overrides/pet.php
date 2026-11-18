<?php

use Doctrine\ORM\Mapping as ORM;

/** @bidirekt schema name=PetCard */
#[ORM\Entity]
class Pet
{
    /**
     * @bidirekt field name=pet_name
     * @var string
     */
    public $petName;

    /** @bidirekt field type=float */
    public $weight;

    /** @bidirekt field optional=true */
    public string $nickname;

    /** @bidirekt field optional=false */
    public string $breed;

    /** @bidirekt field nullable=true */
    public bool $vaccinated;

    /** @bidirekt field nullable=false */
    public ?string $color;

    /** @bidirekt field description="Shown on the card" */
    #[ORM\Column(type: 'text')]
    public string $bio;

    /**
     * @bidirekt field
     * @var list<string>
     */
    public array $tags;

    public string $internalNotes;

    /** @var string */
    public $secret;
}

/** @bidirekt schema */
class Dog extends Pet
{
    public function __construct(
        /** @bidirekt field type=integer */
        public int|float|null $age,
        /**
         * @bidirekt field name=owner_email
         * @var string
         */
        public $ownerEmail,
    ) {}
}
