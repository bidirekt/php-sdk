<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\DeclarationsToContract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Reader\DirectoryToProject;
use Bidirekt\PhpSdk\Reader\ProjectToContract;
use Bidirekt\PhpSdk\Reader\RelativePathToAbsolute;

final class GenerateCommand
{
    public const USAGE = "Usage: php-sdk generate [--check] [--project <dir>] [--out <file>]\n";

    private const DEFAULT_OUTPUT = 'contracts/generated.yaml';

    /**
     * @param list<string> $args the arguments after the program name
     */
    public static function runCommand(array $args, string $cwd): CommandResult
    {
        $options = ArgsToGenerateOptions::argsToGenerateOptions($args);
        if ($options === null) {
            return new CommandResult(1, self::USAGE);
        }
        $real = realpath($cwd);
        if ($real !== false) {
            $cwd = $real;
        }

        return self::generate($options, $cwd);
    }

    private static function generate(GenerateOptions $options, string $cwd): CommandResult
    {
        $directory = $cwd;
        if ($options->project !== null) {
            $directory = RelativePathToAbsolute::relativePathToAbsolute($cwd, $options->project);
            if (!is_dir($directory)) {
                return new CommandResult(1, sprintf("project \"%s\" not found\n", $options->project));
            }
        }
        $project = DirectoryToProject::directoryToProject($directory);
        if ($project instanceof Diagnostic) {
            return new CommandResult(1, DiagnosticsToText::diagnosticToLine($project, '', $cwd));
        }
        $read = ProjectToContract::projectToContract($project);
        $merged = DeclarationsToContract::declarationsToContract($read->contract);
        $errors = [...$read->errors, ...$merged->errors];
        $diagnostics = DiagnosticsToText::diagnosticsToText($errors, $read->warnings, $cwd);
        if ($errors !== []) {
            return new CommandResult(1, $diagnostics);
        }
        if (self::isEmpty($merged->contract)) {
            $source = PathToShown::pathToShown($project->directory, $cwd);
            if ($source === '') {
                $source = '.';
            }

            return new CommandResult(1, sprintf("%sno bidirekt/php-sdk annotation found in \"%s\": nothing to generate\n", $diagnostics, $source));
        }
        $output = RelativePathToAbsolute::relativePathToAbsolute($project->directory, self::DEFAULT_OUTPUT);
        if ($options->out !== null) {
            $output = RelativePathToAbsolute::relativePathToAbsolute($cwd, $options->out);
        }

        return ContractToOutput::contractToOutput($merged->contract, $output, $options->check, $cwd, $diagnostics);
    }

    private static function isEmpty(Contract $contract): bool
    {
        return $contract->provides === [] && $contract->consumes === [] && $contract->schemas === [];
    }
}
