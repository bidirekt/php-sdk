<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class ContractResult
{
    /**
     * @param list<Diagnostic> $errors
     */
    public function __construct(
        public Contract $contract,
        public array $errors,
    ) {}
}
