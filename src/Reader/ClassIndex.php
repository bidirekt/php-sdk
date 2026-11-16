<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

final class ClassIndex
{
    /** @var array<string, list<ClassDeclaration>> lowercase name => declarations in the project files */
    private array $project = [];

    /** @var array<string, list<ClassDeclaration>> lowercase name => declarations in the vendor files read so far */
    private array $vendor = [];

    /** @var array<string, ParsedFile|Diagnostic> */
    private array $vendorFiles = [];

    /** @var array<string, list<Diagnostic>> */
    private array $duplicateErrors = [];

    /**
     * @param list<ParsedFile> $files
     * @param bool $vendorMissing a Composer project whose vendor folder is not installed
     */
    public function __construct(
        array $files,
        private PhpFileToParsed $parser,
        private ?ClassLocator $locator,
        private bool $vendorMissing,
    ) {
        foreach ($files as $file) {
            foreach (self::fileToDeclarations($file, false) as $declaration) {
                $this->project[strtolower($declaration->name)][] = $declaration;
            }
        }
    }

    /**
     * @param list<ParsedFile> $files
     */
    public static function projectToIndex(Project $project, PhpFileToParsed $parser, array $files): self|Diagnostic
    {
        if ($project->composer === null) {
            return new self($files, $parser, null, false);
        }
        $maps = VendorToAutoloadMaps::vendorToAutoloadMaps($project->composer->vendorDirectory);
        if ($maps instanceof Diagnostic) {
            return $maps;
        }
        if ($maps === null) {
            return new self($files, $parser, null, true);
        }

        return new self($files, $parser, new ClassLocator($maps), false);
    }

    /**
     * Every declaration of the class in the project files, the duplicates included.
     *
     * @return list<ClassDeclaration>
     */
    public function projectDeclarations(string $class): array
    {
        return $this->project[strtolower(ltrim($class, '\\'))] ?? [];
    }

    /**
     * The class from the project files, or else from the vendor folder, read on demand.
     *
     * @param SourceLocation $usedAt where the code that needs the class is, for the error when the vendor folder is missing
     */
    public function lookup(string $class, SourceLocation $usedAt): ClassLookup
    {
        $class = ltrim($class, '\\');
        $key = strtolower($class);
        $declarations = $this->project[$key] ?? [];
        if (count($declarations) === 1) {
            return new ClassLookup($declarations[0]);
        }
        if (count($declarations) > 1) {
            $this->duplicateErrors[$key] ??= self::duplicatesToErrors($declarations);

            return new ClassLookup(null, $this->duplicateErrors[$key]);
        }
        if ($this->vendorMissing) {
            return new ClassLookup(null, [new Diagnostic(
                sprintf('cannot find class "%s" in the project, and the vendor folder is not installed: run composer install', $class),
                $usedAt,
            )]);
        }

        return $this->vendorLookup($class, $key);
    }

    private function vendorLookup(string $class, string $key): ClassLookup
    {
        if (!isset($this->vendor[$key])) {
            $file = $this->locator?->classToFile($class);
            if ($file === null) {
                return new ClassLookup(null);
            }
            $parsed = $this->vendorFile($file);
            if ($parsed instanceof Diagnostic) {
                return new ClassLookup(null, [$parsed]);
            }
        }
        $declaration = $this->vendor[$key][0] ?? null;

        return new ClassLookup($declaration);
    }

    private function vendorFile(string $file): ParsedFile|Diagnostic
    {
        $real = (string) realpath($file);
        if (isset($this->vendorFiles[$real])) {
            return $this->vendorFiles[$real];
        }
        $parsed = $this->parser->fileToParsed($file);
        $this->vendorFiles[$real] = $parsed;
        if ($parsed instanceof ParsedFile) {
            foreach (self::fileToDeclarations($parsed, true) as $declaration) {
                $this->vendor[strtolower($declaration->name)][] = $declaration;
            }
        }

        return $parsed;
    }

    /**
     * @return list<ClassDeclaration>
     */
    private static function fileToDeclarations(ParsedFile $file, bool $inVendor): array
    {
        $declarations = [];
        foreach ((new NodeFinder())->findInstanceOf($file->statements, ClassLike::class) as $node) {
            if ($node->namespacedName !== null) {
                $declarations[] = new ClassDeclaration($node->namespacedName->toString(), $node, $file, $inVendor);
            }
        }

        return $declarations;
    }

    /**
     * @param list<ClassDeclaration> $declarations
     * @return list<Diagnostic>
     */
    private static function duplicatesToErrors(array $declarations): array
    {
        $errors = [];
        foreach ($declarations as $declaration) {
            $errors[] = new Diagnostic(
                sprintf('class "%s" is declared in more than one file generate reads: keep a single declaration', $declaration->name),
                $declaration->file->locationOf($declaration->node),
            );
        }

        return $errors;
    }
}
