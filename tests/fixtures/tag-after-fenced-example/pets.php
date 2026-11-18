<?php

/**
 * Finds a pet by its id.
 *
 * @example
 * ```php
 * findPet('42');
 * ```
 *
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200
 * @bidirekt response 404
 */
function findPet(string $id): string
{
    return $id;
}
