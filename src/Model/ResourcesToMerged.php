<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class ResourcesToMerged
{
    /**
     * @param list<ProvidedResource> $provides
     * @return MergedResources<ProvidedResource>
     */
    public static function providedToMerged(array $provides): MergedResources
    {
        $resources = [];
        $errors = [];
        foreach (self::groupBy(
            $provides,
            static fn(ProvidedResource $resource): string => serialize([$resource->method, $resource->path]),
        ) as $group) {
            $first = $group[0];
            $resources[] = $first;
            if (count($group) === 1) {
                continue;
            }
            $message = sprintf(
                '"%s %s" is provided by more than one handler: declare each method and path in one handler',
                $first->method,
                $first->path,
            );
            foreach ($group as $resource) {
                $errors[] = new Diagnostic($message, $resource->location);
            }
        }

        return new MergedResources($resources, $errors);
    }

    /**
     * @param list<ConsumedResource> $consumes
     * @return MergedResources<ConsumedResource>
     */
    public static function consumedToMerged(array $consumes): MergedResources
    {
        $resources = [];
        $errors = [];
        foreach (self::groupBy(
            $consumes,
            static fn(ConsumedResource $resource): string => serialize([$resource->provider, $resource->method, $resource->path]),
        ) as $group) {
            $first = $group[0];
            $resource = sprintf('"%s %s" consumed from "%s"', $first->method, $first->path, $first->provider);
            foreach (array_slice($group, 1) as $other) {
                $conflict = self::requestsToConflict($first, $other, $resource);
                if ($conflict !== null) {
                    $errors[] = new Diagnostic($conflict[0], $conflict[1]);
                    $errors[] = new Diagnostic($conflict[0], $conflict[2]);
                }
            }
            /** @var array<int, Response> $responses */
            $responses = [];
            foreach ($group as $handler) {
                foreach ($handler->responses as $response) {
                    $kept = $responses[$response->status] ?? null;
                    if ($kept === null) {
                        $responses[$response->status] = $response;
                        continue;
                    }
                    if ($kept->body->schema === $response->body->schema) {
                        continue;
                    }
                    $message = sprintf(
                        'response %d of %s is declared as %s and %s: declare one schema with every field the service reads',
                        $response->status,
                        $resource,
                        self::schemaToText($kept->body->schema),
                        self::schemaToText($response->body->schema),
                    );
                    $errors[] = new Diagnostic($message, $kept->body->location);
                    $errors[] = new Diagnostic($message, $response->body->location);
                }
            }
            $resources[] = new ConsumedResource(
                $first->provider,
                $first->method,
                $first->path,
                $first->request,
                array_values($responses),
                $first->location,
            );
        }

        return new MergedResources($resources, $errors);
    }

    /**
     * @return array{string, SourceLocation, SourceLocation}|null
     */
    private static function requestsToConflict(ConsumedResource $first, ConsumedResource $other, string $resource): ?array
    {
        if ($first->request === null && $other->request === null) {
            return null;
        }
        if ($first->request === null || $other->request === null) {
            return [
                sprintf('request of %s is declared in one handler and not in another: declare the same request in every handler', $resource),
                self::requestToLocation($first),
                self::requestToLocation($other),
            ];
        }
        if ($first->request->schema === $other->request->schema) {
            return null;
        }

        return [
            sprintf(
                'request of %s is declared as %s and %s: declare the same request in every handler',
                $resource,
                self::schemaToText($first->request->schema),
                self::schemaToText($other->request->schema),
            ),
            $first->request->location,
            $other->request->location,
        ];
    }

    private static function requestToLocation(ConsumedResource $resource): SourceLocation
    {
        if ($resource->request === null) {
            return $resource->location;
        }

        return $resource->request->location;
    }

    private static function schemaToText(?string $schema): string
    {
        if ($schema === null) {
            return 'null';
        }

        return sprintf('"%s"', $schema);
    }

    /**
     * @template T
     * @param list<T> $items
     * @param callable(T): string $itemToKey
     * @return array<string, non-empty-list<T>>
     */
    private static function groupBy(array $items, callable $itemToKey): array
    {
        $groups = [];
        foreach ($items as $item) {
            $groups[$itemToKey($item)][] = $item;
        }

        return $groups;
    }
}
