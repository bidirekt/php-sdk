<?php

/** @bidirekt schema */
class Owner extends Model
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
    public $email;

    /**
     * @bidirekt field
     * @var Pet[]
     */
    public $pets = array();
}
