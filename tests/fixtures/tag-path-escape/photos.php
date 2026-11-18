<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Photo
{
    #[BidirektField]
    public string $url;
}

/**
 * @bidirekt provides GET /pets/*\/photos
 * @bidirekt response 200 Photo[]
 */
function listPhotos(): array
{
    return [];
}
