<?php

declare(strict_types=1);

namespace App\Http;

use JsonSerializable;

/** @bidirekt schema */
final class ValidationError implements JsonSerializable
{
    /** @bidirekt field */
    public string $message;

    /**
     * @bidirekt field description="The problem with each invalid field by field name"
     * @var array<string, string>
     */
    public array $errors;

    /**
     * @param array<string, string> $errors
     */
    public function __construct(string $message, array $errors)
    {
        $this->message = $message;
        $this->errors = $errors;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['message' => $this->message, 'errors' => (object) $this->errors];
    }
}
