<?php

/** @bidirekt schema @see Owner */
final class Pet
{
    /** @bidirekt field @deprecated use nickname */
    public string $name;

    /** @bidirekt field type=integer @since 2 */
    public int $age;

    /** @bidirekt field description="a @ sign" */
    public string $sign;

    /** @bidirekt field description="code `@x` here" */
    public string $code;

    /**
     * @bidirekt field
     *   description="two lines" @see Owner
     */
    public string $notes;
}

final class PetsController
{
    /**
     * @bidirekt provides GET /pets @see PetsController
     * @bidirekt response 200 Pet[] @deprecated
     */
    public function list(): array
    {
        return [];
    }
}
