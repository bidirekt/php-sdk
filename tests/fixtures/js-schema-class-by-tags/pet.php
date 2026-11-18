<?php

/** @bidirekt schema */
class Pet
{
    /** @bidirekt field type=integer */
    public $id;

    /**
     * @bidirekt field
     * @var string
     */
    public $name;

    /**
     * @bidirekt field name=birth_date
     * @var string|null
     */
    public $birthDate;

    /** @var string */
    public $internalNotes;
}
