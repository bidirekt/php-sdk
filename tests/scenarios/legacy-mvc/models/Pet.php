<?php

/**
 * A pet of the store.
 *
 * @bidirekt schema
 */
class Pet extends Model
{
    /**
     * @bidirekt field
     * @var string
     */
    public $name;

    /**
     * @bidirekt field
     * @var string|null
     */
    public $nickname;

    /**
     * @bidirekt field description="available pending or sold"
     * @var string
     */
    public $status = 'available';

    /**
     * @bidirekt field
     * @var float
     */
    public $weight;

    /**
     * @bidirekt field
     * @var boolean
     */
    public $vaccinated = false;

    /**
     * @bidirekt field
     * @var integer
     */
    public $owner_id;

    /**
     * @bidirekt field
     * @var Category|null
     */
    public $category;

    /**
     * @bidirekt field
     * @var string[]
     */
    public $tags = array();

    /**
     * Extra facts shown on the pet's page, by label.
     *
     * @bidirekt field
     * @var array<string, string>
     */
    public $attributes = array();

    /**
     * @var string
     */
    public $internalNotes;
}
