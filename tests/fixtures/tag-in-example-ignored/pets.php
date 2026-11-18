<?php

/**
 * Lists the pets.
 * @bidirekt provides GET /pets
 * @bidirekt response 200
 * @example
 * @bidirekt provides GET /example
 * @bidirekt response 201
 * @Bidirekt provides GET /typo
 * @return void
 */
function listPets(): void {}

/**
 * Documents how to tag a handler.
 * @example
 * ```php
 * /** @bidirekt provides GET /owners *\/
 * ```
 * @example
 * @bidirekt provides GET /owners
 */
function documented(): void {}
