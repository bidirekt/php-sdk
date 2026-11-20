<?php

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field description="The pet's age, in years." */
    public int $age;

    /**
     * @bidirekt field description="The name
     *   the owner calls the pet"
     */
    public string $name;
}

final class HealthController
{
    /**
     * @bidirekt provides GET /health
     * @bidirekt response 200 Health
     * @bidirekt schema Health
     * @bidirekt field status type=string description="Shown on the status page."
     */
    public function health(): void {}
}
