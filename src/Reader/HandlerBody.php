<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Body;
use Bidirekt\PhpSdk\Model\NamedSchema;

final class HandlerBody
{
    /**
     * @param NamedSchema|null $list the list schema the body declares, as in `[Pet::class]`
     */
    public function __construct(
        public Body $body,
        public ?NamedSchema $list,
    ) {}
}
