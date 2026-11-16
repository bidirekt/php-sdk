<?php

/**
 * @bidirekt provides GET /owners/{ id }
 * @bidirekt response 200
 */
function findOwner(): void
{
}

/**
 * @bidirekt consumes {payments api} POST /charges
 * @bidirekt response 201
 */
function charge(): void
{
}

/** @bidirekt schema name={Pet Status} */
final class Status
{
    /** @bidirekt field */
    public string $code;
}
