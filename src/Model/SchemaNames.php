<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Model;

final class SchemaNames
{
    public static function schemaToListName(string $schema): string
    {
        return $schema . 'List';
    }

    public static function listToNamedSchema(string $schema, ?string $name, SourceLocation $location): NamedSchema
    {
        return new NamedSchema(
            $name ?? self::schemaToListName($schema),
            new ArrayNode(new RefNode($schema)),
            $location,
        );
    }
}
