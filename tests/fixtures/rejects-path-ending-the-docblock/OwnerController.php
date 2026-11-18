<?php

final class OwnerController
{
    /**
     * @bidirekt provides GET /owners/*
     * @bidirekt response 200
     */
    public function find(): void {}

    /**
     * Lists the pets of an owner.
     *
     * @bidirekt provides GET /owners/*/pets
     * @bidirekt response 200
     */
    public function pets(): void {}
}
