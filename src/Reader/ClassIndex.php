<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Composer\Autoload\ClassLoader;
use ReflectionClass;

final class ClassIndex
{
    /** @var array<string, list<DeclaredClass>> lowercase name => where the project files read so far declare it */
    private array $project = [];

    /** @var array<string, list<string>> lowercase short name => the unparsed project files that may declare a class under it */
    private array $candidates = [];

    /** @var array<string, true> paths of the unparsed files whose classes are read */
    private array $readFiles = [];

    /** @var array<string, list<Diagnostic>> */
    private array $duplicateErrors = [];

    private bool $vendorRead = false;

    private ClassLoader|Diagnostic|null $vendor = null;

    /** @var array<string, true> paths of the files read for a class the vendor folder declares */
    private array $vendorFiles = [];

    /**
     * @param string|null $vendorDirectory the Composer project's vendor folder, null without a `composer.json`
     * @param list<string> $requiredPackages the packages its `composer.json` requires
     */
    public function __construct(
        private ParsedFiles $files,
        private ?string $vendorDirectory,
        private array $requiredPackages = [],
    ) {}

    /**
     * How to fix a class found nowhere: install the required packages Composer has not installed, one of which
     * may declare it, or else declare it.
     */
    public function missingClassFix(): string
    {
        $installed = $this->installedPackages();
        $missing = [];
        foreach ($this->requiredPackages as $package) {
            if ($installed !== null && !isset($installed[strtolower($package)])) {
                $missing[] = sprintf('"%s"', $package);
            }
        }
        if ($missing === []) {
            return 'declare it in the project or require its package with composer';
        }

        return sprintf('run composer install to install %s, which composer.json requires', implode(', ', $missing));
    }

    /**
     * @return array<string, true>|null the packages of the vendor folder's `composer/installed.json`, in lowercase, null without one
     */
    private function installedPackages(): ?array
    {
        if ($this->vendorDirectory === null) {
            return null;
        }
        $json = json_decode((string) @file_get_contents($this->vendorDirectory . '/composer/installed.json'), true);
        if (!is_array($json)) {
            return null;
        }
        $packages = $json['packages'] ?? $json;
        if (!is_array($packages)) {
            return null;
        }
        $installed = [];
        foreach ($packages as $package) {
            if (is_array($package) && is_string($package['name'] ?? null)) {
                $installed[strtolower($package['name'])] = true;
            }
        }

        return $installed;
    }

    public function addFileSummary(FileSummary $file): void
    {
        foreach ($file->classDeclarations() as $declaration) {
            $this->project[strtolower($declaration->name)][] = new DeclaredClass($declaration->name, $declaration->summary->location);
        }
    }

    /**
     * Its classes are read when a class under one of its names is needed.
     */
    public function addUnparsedFile(UnparsedFile $file): void
    {
        foreach ($file->names as $name) {
            $this->candidates[$name][] = $file->path;
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
        $declared = $this->projectDeclarations($key);
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
        $declared = $this->projectDeclarations($key);
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

    /**
     * The first declaration of the class in each project file, the unparsed files that may declare it read first,
     * so the declarations come in the order of the files, as if every file had been read.
     *
     * @return list<DeclaredClass>
     */
    private function projectDeclarations(string $key): array
    {
        $name = strtolower(SchemaNaming::classToShortName($key));
        $paths = $this->candidates[$name] ?? [];
        unset($this->candidates[$name]);
        $read = [];
        foreach ($paths as $path) {
            if (isset($this->readFiles[$path])) {
                continue;
            }
            $this->readFiles[$path] = true;
            foreach (ParsedFiles::pathToDeclaredClasses($path) as $class) {
                $classKey = strtolower($class->name);
                $this->project[$classKey][] = $class;
                $read[$classKey] = true;
            }
        }
        foreach (array_keys($read) as $classKey) {
            usort($this->project[$classKey], static fn(DeclaredClass $a, DeclaredClass $b): int => strcmp((string) $a->location->file, (string) $b->location->file));
        }

        $byFile = [];
        foreach ($this->project[$key] ?? [] as $class) {
            $byFile[(string) $class->location->file] ??= $class;
        }

        return array_values($byFile);
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
