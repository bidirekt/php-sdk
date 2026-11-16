<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node\Attribute;

final class SdkAttribute
{
    /**
     * @param string $name the SDK attribute's short name, as the SDK declares it ("BidirektField")
     */
    public function __construct(
        public string $name,
        public Attribute $attribute,
        public SourceLocation $location,
    ) {}
}
