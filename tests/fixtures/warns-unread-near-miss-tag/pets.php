<?php

$pets = $app
    /** @Bidirekt provides GET /pets */
    ->get('/pets');

$toys = $app
    /**
     * @bidirektprovides GET /toys
     */
    ->get('/toys');

$owners = $app
    /** Read by @bidirekt/php-sdk, like @Bidirekt tags. */
    ->get('/owners');

$people = $app
    /** @BidirektProvider is an attribute. */
    ->get('/people');

/**
 * @bidirekt provides GET /owners
 * @bidirekt response 200
 */
function listOwners(): void {}
