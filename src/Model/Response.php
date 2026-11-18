<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class Response
{
    public function __construct(
        public int $status,
        public Body $body,
    ) {}
}
