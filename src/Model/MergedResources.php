<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

/**
 * @template T of ProvidedResource|ConsumedResource
 */
final class MergedResources
{
    /**
     * @param list<T> $resources
     * @param list<Diagnostic> $errors
     */
    public function __construct(
        public array $resources,
        public array $errors,
    ) {}
}
