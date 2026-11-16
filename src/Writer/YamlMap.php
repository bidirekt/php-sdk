<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Writer;

/**
 * A list of strings is a flow sequence of double-quoted scalars.
 *
 * @phpstan-type Value YamlMap|list<string>|string|bool|null
 */
final class YamlMap
{
    /**
     * @var list<array{int|string, Value}>
     */
    public array $pairs = [];

    /**
     * @param Value $value
     */
    public function add(int|string $key, YamlMap|array|string|bool|null $value): void
    {
        $this->pairs[] = [$key, $value];
    }
}
