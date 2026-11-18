<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Writer\ContractToYaml;

final class ContractToOutput
{
    /**
     * Writes the contract's YAML to the output, or with `$check` compares it with the file there without writing.
     *
     * @param string $diagnostics the text that comes before any message of its own
     */
    public static function contractToOutput(Contract $contract, string $output, bool $check, string $cwd, string $diagnostics): CommandResult
    {
        $yaml = ContractToYaml::contractToYaml($contract);
        $shownOutput = PathToShown::pathToShown($output, $cwd);
        if ($check) {
            return self::check($output, $shownOutput, $yaml, $diagnostics);
        }
        if (is_dir($output)) {
            return new CommandResult(1, sprintf("%scannot write \"%s\": it is a folder\n", $diagnostics, $shownOutput));
        }
        if ((!is_dir(dirname($output)) && !@mkdir(dirname($output), 0777, true)) || @file_put_contents($output, $yaml) === false) {
            return new CommandResult(1, sprintf("%scannot write \"%s\": %s\n", $diagnostics, $shownOutput, self::lastErrorToReason()));
        }

        return new CommandResult(0, $diagnostics);
    }

    private static function check(string $output, string $shownOutput, string $yaml, string $diagnostics): CommandResult
    {
        if (!file_exists($output)) {
            return new CommandResult(1, sprintf("%s\"%s\" does not exist: run generate without --check\n", $diagnostics, $shownOutput));
        }
        if (is_dir($output)) {
            return new CommandResult(1, sprintf("%scannot read \"%s\": it is a folder\n", $diagnostics, $shownOutput));
        }
        $written = @file_get_contents($output);
        if ($written === false) {
            return new CommandResult(1, sprintf("%scannot read \"%s\": %s\n", $diagnostics, $shownOutput, self::lastErrorToReason()));
        }
        if (str_replace("\r\n", "\n", $written) !== $yaml) {
            return new CommandResult(1, sprintf("%s\"%s\" is out of date: run generate without --check\n", $diagnostics, $shownOutput));
        }

        return new CommandResult(0, $diagnostics);
    }

    private static function lastErrorToReason(): string
    {
        $message = error_get_last()['message'] ?? 'unknown error';
        $call = strpos($message, '): ');
        if ($call !== false) {
            $message = substr($message, $call + 3);
        }

        return lcfirst($message);
    }
}
