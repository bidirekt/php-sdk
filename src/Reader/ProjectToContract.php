<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;

final class ProjectToContract
{
    /**
     * Each step reads the project files one by one, so only the most recently read are kept parsed.
     */
    public static function projectToContract(Project $project): ReadResult
    {
        $files = new ParsedFiles();
        $classes = new ClassIndex($files, $project->composer?->vendorDirectory);
        $paths = [];
        $syntaxErrors = [];
        $tagged = new TaggedComments();
        $schemaTags = new SchemaTags();
        $tagHandlers = new ReadResult(new Contract(), [], []);
        $attributeWarnings = [];
        $projectFiles = ProjectToFiles::projectToFiles($project);
        foreach ($projectFiles->paths as $path) {
            $read = $files->projectFileToRead($path);
            if ($read instanceof Diagnostic) {
                $syntaxErrors[] = $read;

                continue;
            }
            if (is_array($read)) {
                $classes->addDeclaredClasses($read);

                continue;
            }
            $paths[] = $path;
            $classes->addParsedFile($read);
            $fileTagged = FilesToTaggedComments::filesToTaggedComments([$read]);
            array_push($tagged->errors, ...$fileTagged->errors);
            array_push($tagged->warnings, ...$fileTagged->warnings);
            $schemaTags->addProjectFile($read, CommentsToSchemaTags::commentsToSchemaTags($fileTagged->comments));
            $fileHandlers = TagsToResources::commentsToResources($fileTagged->comments);
            array_push($tagHandlers->contract->provides, ...$fileHandlers->contract->provides);
            array_push($tagHandlers->contract->consumes, ...$fileHandlers->contract->consumes);
            array_push($tagHandlers->contract->schemas, ...$fileHandlers->contract->schemas);
            array_push($tagHandlers->errors, ...$fileHandlers->errors);
            array_push($attributeWarnings, ...SdkAttributes::fileToMissingUseWarnings($read), ...SdkAttributes::fileToMisplacedWarnings($read));
        }
        if ($syntaxErrors !== []) {
            return new ReadResult(new Contract(), $syntaxErrors, $projectFiles->warnings);
        }
        $evaluator = new ConstantExpressionToValue($classes);
        $schemaClasses = new SchemaClasses($evaluator, $schemaTags);
        $schemas = ClassesToSchemas::filesToSchemas($paths, $files, $classes, $schemaClasses, $schemaTags);
        $handlers = (new HandlersToResources($evaluator, new TypeToSchemaNode($classes, $schemaClasses)))->filesToResources($paths, $files);
        $warnings = [...$projectFiles->warnings, ...$tagged->warnings, ...$schemaTags->warnings, ...$schemas->warnings, ...$attributeWarnings];
        $contract = new Contract(
            [...$handlers->contract->provides, ...$tagHandlers->contract->provides],
            [...$handlers->contract->consumes, ...$tagHandlers->contract->consumes],
            [...$schemas->contract->schemas, ...$handlers->contract->schemas, ...$tagHandlers->contract->schemas, ...$schemaClasses->enumSchemas()],
        );
        $errors = [...$tagged->errors, ...$schemaTags->errors, ...$schemas->errors, ...$handlers->errors, ...$tagHandlers->errors];

        return new ReadResult($contract, self::diagnosticsToUnique($errors), self::diagnosticsToUnique($warnings));
    }

    /**
     * A diagnostic the readers reach more than once, like an enum's from a field and from a handler, is reported once.
     *
     * @param list<Diagnostic> $diagnostics
     * @return list<Diagnostic>
     */
    private static function diagnosticsToUnique(array $diagnostics): array
    {
        $unique = [];
        foreach ($diagnostics as $diagnostic) {
            $unique[sprintf('%s:%d %s', $diagnostic->location->file ?? '', $diagnostic->location->line ?? 0, $diagnostic->message)] = $diagnostic;
        }

        return array_values($unique);
    }
}
