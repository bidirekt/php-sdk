<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Composer\Autoload\ClassLoader;
use ReflectionClass;

final class ClassIndex
{
    /** @var array<string, list<DeclaredClass>> lowercase name => where the project files declare it */
    private array $project = [];

    /** @var array<string, list<Diagnostic>> */
    private array $duplicateErrors = [];

    private bool $vendorRead = false;

    private ClassLoader|Diagnostic|null $vendor = null;

    /** @var array<string, true> paths of the files read for a class the vendor folder declares */
    private array $vendorFiles = [];

    /**
     * @param string|null $vendorDirectory the Composer project's vendor folder, null without a `composer.json`
     */
    public function __construct(
        private ParsedFiles $files,
        private ?string $vendorDirectory,
    ) {}

    public function addFileSummary(FileSummary $file): void
    {
        foreach ($file->classDeclarations() as $declaration) {
            $this->project[strtolower($declaration->name)][] = new DeclaredClass($declaration->name, $declaration->summary->location);
        }
    }

    /**
     * @param list<DeclaredClass> $classes of a project file read again when one of them is looked up
     */
    public function addDeclaredClasses(array $classes): void
    {
        foreach ($classes as $class) {
            $this->project[strtolower($class->name)][] = $class;
        }
    }

    /**
     * The class from the project files, or else from the vendor folder, read on demand: none for a class PHP declares.
     *
     * @param SourceLocation $usedAt where the code that needs the class is, for the error when the vendor folder is missing
     */
    public function lookup(string $class, SourceLocation $usedAt): ClassLookup
    {
        $class = ltrim($class, '\\');
        $key = strtolower($class);
        $declared = $this->project[$key] ?? [];
        if (count($declared) === 1) {
            return $this->fileLookup((string) $declared[0]->location->file, $key);
        }
        if (count($declared) > 1) {
            return new ClassLookup(null, $this->duplicateErrors($class));
        }
        if ($this->vendorDirectory === null || self::isBuiltIn($class, true)) {
            return new ClassLookup(null);
        }
        if (!$this->vendorRead) {
            $this->vendor = VendorToClassLoader::vendorToClassLoader($this->vendorDirectory);
            $this->vendorRead = true;
        }
        if ($this->vendor === null) {
            return new ClassLookup(null, [new Diagnostic(
                sprintf('cannot find class "%s" in the project, and the vendor folder is not installed: run composer install', $class),
                $usedAt,
            )]);
        }
        if ($this->vendor instanceof Diagnostic) {
            return new ClassLookup(null, [$this->vendor]);
        }
        $file = $this->vendor->findFile($class);
        if ($file === false) {
            return new ClassLookup(null);
        }

        $lookup = $this->fileLookup($file, $key);
        if ($lookup->declaration !== null) {
            $this->vendorFiles[$lookup->declaration->file->path] = true;
        }

        return $lookup;
    }

    /**
     * The errors of a project class declared in more than one file, none when it is declared once.
     *
     * @return list<Diagnostic>
     */
    public function duplicateErrors(string $class): array
    {
        $key = strtolower(ltrim($class, '\\'));
        $declared = $this->project[$key] ?? [];
        if (count($declared) < 2) {
            return [];
        }

        return $this->duplicateErrors[$key] ??= self::duplicatesToErrors($declared);
    }

    /**
     * Whether the class was found through the vendor folder, so the developer cannot annotate it.
     */
    public function isVendorClass(ClassDeclaration $class): bool
    {
        return isset($this->vendorFiles[$class->file->path]);
    }

    /**
     * Whether PHP itself declares the class, or the interface when interfaces are asked for.
     */
    public static function isBuiltIn(string $class, bool $orInterface): bool
    {
        if (!class_exists($class, false) && (!$orInterface || !interface_exists($class, false))) {
            return false;
        }

        return (new ReflectionClass($class))->isInternal();
    }

    private function fileLookup(string $path, string $key): ClassLookup
    {
        $summary = $this->files->pathToSummary($path);
        if ($summary instanceof Diagnostic) {
            return new ClassLookup(null, [$summary]);
        }

        return new ClassLookup($summary->classDeclarationNamed($key));
    }

    /**
     * @param list<DeclaredClass> $declared
     * @return list<Diagnostic>
     */
    private static function duplicatesToErrors(array $declared): array
    {
        $errors = [];
        foreach ($declared as $class) {
            $errors[] = new Diagnostic(
                sprintf('class "%s" is declared in more than one file generate reads: keep a single declaration', $class->name),
                $class->location,
            );
        }

        return $errors;
    }
}
