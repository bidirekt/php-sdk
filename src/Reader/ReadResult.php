<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;

final class ReadResult
{
    /**
     * @param Contract $contract the declarations as read, duplicates included: the model rules merge them
     * @param list<Diagnostic> $errors
     * @param list<Diagnostic> $warnings
     */
    public function __construct(
        public Contract $contract,
        public array $errors,
        public array $warnings,
    ) {}
}
