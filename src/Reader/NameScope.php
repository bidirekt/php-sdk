<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;

final class NameScope
{
    public const ATTRIBUTE = 'bidirektNameScope';

    /** @var array<string, string> lowercase alias => fully-qualified name */
    private array $aliases = [];

    public function __construct(
        public string $namespace,
    ) {}

    public static function ofNode(Node $node): self
    {
        $scope = $node->getAttribute(self::ATTRIBUTE);
        if ($scope instanceof self) {
            return $scope;
        }

        return new self('');
    }

    public function addAlias(string $alias, string $fullyQualified): void
    {
        $this->aliases[strtolower($alias)] = ltrim($fullyQualified, '\\');
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliases[strtolower($alias)]);
    }

    public function classNameToFullyQualified(string $written): string
    {
        if (str_starts_with($written, '\\')) {
            return substr($written, 1);
        }
        if (in_array(strtolower($written), ['self', 'static', 'parent'], true)) {
            return $written;
        }
        $parts = explode('\\', $written, 2);
        $first = strtolower($parts[0]);
        if ($first === 'namespace' && isset($parts[1])) {
            return $this->prefixNamespace($parts[1]);
        }
        if (isset($this->aliases[$first])) {
            if (isset($parts[1])) {
                return $this->aliases[$first] . '\\' . $parts[1];
            }

            return $this->aliases[$first];
        }

        return $this->prefixNamespace($written);
    }

    private function prefixNamespace(string $name): string
    {
        if ($this->namespace === '') {
            return $name;
        }

        return $this->namespace . '\\' . $name;
    }
}
