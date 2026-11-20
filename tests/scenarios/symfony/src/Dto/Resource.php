<?php

namespace App\Dto;

use Bidirekt\PhpSdk\BidirektField;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

abstract class Resource
{
    public function __construct(
        #[BidirektField]
        public readonly int $id,
        #[BidirektField(type: 'string', description: 'When it was created in RFC 3339')]
        #[Context([DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::RFC3339])]
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
