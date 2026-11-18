<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * The classes the schema names of `@bidirekt request` and `@bidirekt response` bodies resolve to by `use` and
 * namespace, for the error that names the class when no schema has the name and the class has no schema.
 */
final class TagBodyClasses
{
    /** @var list<array{string, string, SourceLocation}> schema name, class, the tag's location */
    private array $bodies = [];

    public function addBody(string $schema, string $class, SourceLocation $location): void
    {
        $this->bodies[] = [$schema, $class, $location];
    }

    /**
     * @param list<NamedSchema> $schemas every schema the contract declares
     * @return array{list<NamedSchema>, list<Diagnostic>} a schema with no field for each name a project class without
     *                                                    a schema took, so the bodies naming it add no error of their own, and the errors
     */
    public function bodiesToErrors(array $schemas, ClassIndex $classes, SchemaClasses $schemaClasses): array
    {
        $declared = [];
        foreach ($schemas as $schema) {
            $declared[$schema->name] = true;
        }
        $placeholders = [];
        $errors = [];
        foreach ($this->bodies as [$schema, $class, $location]) {
            if (isset($declared[$schema])) {
                continue;
            }
            $lookup = $classes->lookup($class, $location);
            $declaration = $lookup->declaration;
            if ($declaration === null || $declaration->summary->kind !== 'class' || $classes->isVendorClass($declaration) || $schemaClasses->classToName($declaration) !== null) {
                continue;
            }
            $errors[] = new Diagnostic(sprintf('cannot translate %s: %s', $declaration->summary->description, AnnotationForm::tag()->addSchema), $location);
            $placeholders[$schema] ??= new NamedSchema($schema, new ObjectNode([]), $location);
        }

        return [array_values($placeholders), $errors];
    }
}
