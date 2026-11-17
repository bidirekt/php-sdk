<?php

declare(strict_types=1);

namespace App\Http;

use JsonSerializable;

final class ValidationError implements JsonSerializable
{
    public string $message;

    /**
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
