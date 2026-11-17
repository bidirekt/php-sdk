<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * What the reader reads of a file once its tree is gone: its class-likes, its handlers' attributes, its
 * annotated properties and parameters, and its schema and field tags.
 */
final class FileSummary
{
    /** @var array<string, ClassDeclaration>|null lowercase name => its first declaration in the file */
    private ?array $declarations = null;

    /**
     * @param list<ClassSummary> $classes in the order they are written, anonymous classes included
     * @param list<list<SdkAttribute>> $handlers the SDK attributes of each method, function or closure that has some
     * @param list<FieldSite> $fieldSites
     */
    public function __construct(
        public string $path,
        public array $classes,
        public array $handlers,
        public array $fieldSites,
        public SchemaTags $tags,
    ) {}

    /**
     * @return list<ClassDeclaration> the named class-likes, in the order they are written
     */
    public function classDeclarations(): array
    {
        $declarations = [];
        foreach ($this->classes as $class) {
            if ($class->name !== null) {
                $declarations[] = new ClassDeclaration($class->name, $class, $this);
            }
        }

        return $declarations;
    }

    public function classDeclarationNamed(string $lowercaseName): ?ClassDeclaration
    {
        if ($this->declarations === null) {
            $this->declarations = [];
            foreach ($this->classDeclarations() as $declaration) {
                $this->declarations[strtolower($declaration->name)] ??= $declaration;
            }
        }

        return $this->declarations[$lowercaseName] ?? null;
    }
}
