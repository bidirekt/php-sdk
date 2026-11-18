<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class DeclarationsToContract
{
    public static function declarationsToContract(Contract $declarations): ContractResult
    {
        [$schemas, $schemaErrors] = self::declarationsToSchemas($declarations->schemas);
        $provides = ResourcesToMerged::providedToMerged($declarations->provides);
        $consumes = ResourcesToMerged::consumedToMerged($declarations->consumes);
        $contract = new Contract($provides->resources, $consumes->resources, $schemas);
        $declared = [];
        foreach ($declarations->schemas as $schema) {
            $declared[$schema->name] = true;
        }
        $duplicateFields = [];
        foreach ($declarations->schemas as $schema) {
            $duplicateFields = [...$duplicateFields, ...self::schemaToDuplicateFields($schema)];
        }

        return new ContractResult($contract, [
            ...$schemaErrors,
            ...$duplicateFields,
            ...$provides->errors,
            ...$consumes->errors,
            ...self::contractToMissingReferences($contract, $declared),
        ]);
    }

    /**
     * @param list<NamedSchema> $declarations
     * @return array{list<NamedSchema>, list<Diagnostic>}
     */
    private static function declarationsToSchemas(array $declarations): array
    {
        /** @var array<string, non-empty-list<NamedSchema>> $groups */
        $groups = [];
        foreach ($declarations as $declaration) {
            $groups[$declaration->name][] = $declaration;
        }
        $schemas = [];
        $errors = [];
        foreach ($groups as $group) {
            $first = $group[0];
            if (self::isEveryNodeSameList($group, $first->node)) {
                $schemas[] = $first;
                continue;
            }
            $message = self::collisionToMessage($first->name, $group);
            foreach ($group as $declaration) {
                $errors[] = new Diagnostic($message, $declaration->location);
            }
        }

        return [$schemas, $errors];
    }

    /**
     * @return list<Diagnostic>
     */
    private static function schemaToDuplicateFields(NamedSchema $schema): array
    {
        if (!$schema->node instanceof ObjectNode) {
            return [];
        }
        $counts = [];
        foreach ($schema->node->properties as $property) {
            $counts[$property->name] = ($counts[$property->name] ?? 0) + 1;
        }
        $errors = [];
        foreach ($schema->node->properties as $property) {
            if ($counts[$property->name] === 1) {
                continue;
            }
            $errors[] = new Diagnostic(
                sprintf(
                    'field "%s" is declared more than once in schema "%s": give each field a distinct name',
                    $property->name,
                    $schema->name,
                ),
                $property->location,
            );
        }

        return $errors;
    }

    /**
     * @param list<NamedSchema> $group
     */
    private static function isEveryNodeSameList(array $group, SchemaNode $first): bool
    {
        foreach ($group as $declaration) {
            if (!self::isSameList($declaration->node, $first)) {
                return false;
            }
        }

        return true;
    }

    private static function isSameList(SchemaNode $node, SchemaNode $first): bool
    {
        if ($node === $first) {
            return true;
        }

        return $node instanceof ArrayNode && var_export($node, true) === var_export($first, true);
    }

    /**
     * @param list<NamedSchema> $group
     */
    private static function collisionToMessage(string $name, array $group): string
    {
        $lists = array_filter($group, static fn(NamedSchema $schema): bool => $schema->node instanceof ArrayNode);
        if (count($lists) === 0) {
            return sprintf('schema name "%s" is declared more than once: give each schema a distinct name', $name);
        }
        if (count($lists) === count($group)) {
            return sprintf('lists named "%s" differ: give each list a distinct name', $name);
        }

        return sprintf('list name "%s" is also the name of another schema: give the list a distinct name', $name);
    }

    /**
     * @param array<string, true> $declared
     * @return list<Diagnostic>
     */
    private static function contractToMissingReferences(Contract $contract, array $declared): array
    {
        $references = [];
        foreach ([...$contract->provides, ...$contract->consumes] as $resource) {
            $bodies = array_map(static fn(Response $response): Body => $response->body, $resource->responses);
            if ($resource->request !== null) {
                array_unshift($bodies, $resource->request);
            }
            foreach ($bodies as $body) {
                if ($body->schema !== null) {
                    $references[] = [$body->schema, $body->location];
                }
            }
        }
        foreach ($contract->schemas as $schema) {
            $references = [...$references, ...self::nodeToReferences($schema->node, $schema->location)];
        }
        $errors = [];
        foreach ($references as [$schema, $location]) {
            if (isset($declared[$schema])) {
                continue;
            }
            $errors[] = new Diagnostic(
                sprintf('schema "%s" is not declared: reference a schema by its name in the contract', $schema),
                $location,
            );
        }

        return $errors;
    }

    /**
     * @return list<array{string, SourceLocation}>
     */
    private static function nodeToReferences(SchemaNode $node, SourceLocation $location): array
    {
        if ($node instanceof RefNode) {
            return [[$node->schema, $location]];
        }
        if ($node instanceof ObjectNode) {
            $references = [];
            foreach ($node->properties as $property) {
                $references = [...$references, ...self::nodeToReferences($property->node, $property->location)];
            }

            return $references;
        }
        if ($node instanceof ArrayNode) {
            return self::nodeToReferences($node->items, $location);
        }
        if ($node instanceof MapNode) {
            return self::nodeToReferences($node->values, $location);
        }
        if ($node instanceof UnionNode) {
            $references = [];
            foreach ($node->members as $member) {
                $references = [...$references, ...self::nodeToReferences($member->node, $location)];
            }

            return $references;
        }

        return [];
    }
}
