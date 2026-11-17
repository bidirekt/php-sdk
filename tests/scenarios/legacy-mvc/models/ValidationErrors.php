<?php

/** @bidirekt schema */
class ValidationErrors
{
    /**
     * @bidirekt field
     * @var string
     */
    public $message;

    /**
     * The problem with each invalid field, by field name.
     *
     * @bidirekt field
     * @var array<string, string>
     */
    public $errors = array();

    /**
     * @param string $message
     * @param array $errors
     */
    public function __construct($message, $errors)
    {
        $this->message = $message;
        $this->errors = $errors;
    }
}
