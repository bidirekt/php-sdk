<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;

final class ProjectToContract
{
    /**
     * Each project file that mentions the SDK is parsed once and read into its summary, which the later steps
     * read once every class is indexed.
     */
    public static function projectToContract(Project $project): ReadResult
    {
        $files = new ParsedFiles();
        $classes = new ClassIndex($files, $project->composer?->vendorDirectory);
        $summaries = [];
        $syntaxErrors = [];
        $tagErrors = [];
        $tagWarnings = [];
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
            if ($read instanceof UnparsedFile) {
                $classes->addUnparsedFile($read);

                continue;
            }
            $fileTagged = FileToTaggedComments::fileToTaggedComments($read);
            array_push($tagErrors, ...$fileTagged->errors);
            array_push($tagWarnings, ...$fileTagged->warnings);
            $summary = $files->addProjectFile($read, CommentsToSchemaTags::commentsToSchemaTags($fileTagged->comments));
            $summaries[] = $summary;
            $classes->addFileSummary($summary);
            $schemaTags->addProjectFile($summary);
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
        $types = new TypeToSchemaNode($classes, $schemaClasses);
        $declaredTypes = new DeclaredTypeToSchemaNode($types);
        $declaredFields = new FieldsToProperties($declaredTypes);
        $schemas = ClassesToSchemas::filesToSchemas($summaries, $classes, $schemaClasses, $schemaTags, $evaluator, new FieldToSchemaNode($types, $declaredTypes), $declaredFields);
        $handlers = (new HandlersToResources($evaluator, $types, $declaredFields))->filesToResources($summaries);
        $warnings = [...$projectFiles->warnings, ...$tagWarnings, ...$schemaTags->warnings, ...$schemas->warnings, ...$attributeWarnings];
        $contract = new Contract(
            [...$handlers->contract->provides, ...$tagHandlers->contract->provides],
            [...$handlers->contract->consumes, ...$tagHandlers->contract->consumes],
            [...$schemas->contract->schemas, ...$handlers->contract->schemas, ...$tagHandlers->contract->schemas, ...$schemaClasses->enumSchemas()],
        );
        $errors = [...$tagErrors, ...$schemaTags->errors, ...$schemas->errors, ...$handlers->errors, ...$tagHandlers->errors];

        return new ReadResult($contract, $errors, $warnings);
    }
}
