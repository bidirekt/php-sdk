<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;

final class ProjectToContract
{
    public static function projectToContract(Project $project): ReadResult
    {
        $parser = new PhpFileToParsed();
        $classes = new ClassIndex($parser, $project->composer?->vendorDirectory);
        $files = [];
        $syntaxErrors = [];
        $projectFiles = ProjectToFiles::projectToFiles($project);
        foreach ($projectFiles->paths as $path) {
            $read = $parser->projectFileToRead($path);
            if ($read instanceof Diagnostic) {
                $syntaxErrors[] = $read;

                continue;
            }
            if ($read instanceof ParsedFile) {
                $files[] = $read;
                $classes->addParsedFile($read);

                continue;
            }
            $classes->addDeclaredClasses($read);
        }
        if ($syntaxErrors !== []) {
            return new ReadResult(new Contract(), $syntaxErrors, $projectFiles->warnings);
        }
        $evaluator = new ConstantExpressionToValue($classes);
        $tagged = FilesToTaggedComments::filesToTaggedComments($files);
        $schemaTags = CommentsToSchemaTags::commentsToSchemaTags($tagged->comments);
        $schemaTags->addReadFiles($files);
        $schemaClasses = new SchemaClasses($evaluator, $schemaTags);
        $schemas = ClassesToSchemas::filesToSchemas($files, $classes, $schemaClasses, $schemaTags);
        $handlers = (new HandlersToResources($evaluator, new TypeToSchemaNode($classes, $schemaClasses)))->filesToResources($files);
        $tagHandlers = TagsToResources::commentsToResources($tagged->comments);
        $warnings = [...$projectFiles->warnings, ...$tagged->warnings, ...$schemaTags->warnings, ...$schemas->warnings];
        foreach ($files as $file) {
            array_push($warnings, ...SdkAttributes::fileToMissingUseWarnings($file), ...SdkAttributes::fileToMisplacedWarnings($file));
        }
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
