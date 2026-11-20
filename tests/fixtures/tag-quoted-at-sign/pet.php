<?php

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field description="mail me @home" */
    public string $email;

    /** @bidirekt field description="ask @vet first" @see Owner */
    public string $health;

    /**
     * @bidirekt field description="seen at
     *   noon @daily" @since 2
     */
    public string $walks;

    /** @bidirekt field description="Hello, world! (a.k.a. 'hi'); 50% off: yes? #1 & more." */
    public string $greeting;

    /** @bidirekt field description="a @ sign" */
    public string $sign;

    /** @bidirekt field description="code `@x` here" */
    public string $code;
}

final class PetsController
{
    /**
     * @bidirekt provides GET /pets
     * @bidirekt response 200 Pet[]
     */
    public function list(): array
    {
        return [];
    }
}
