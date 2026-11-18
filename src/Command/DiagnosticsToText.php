<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Command;

use Bidirekt\PhpSdk\Model\Diagnostic;

final class DiagnosticsToText
{
    /**
     * One line per diagnostic, `<file>[:<line>] [warning: ]<message>`, errors and warnings sorted together by
     * file (byte order) and line, with the file relative to the cwd. A diagnostic reached more than once, like
     * an enum's from a field and from a handler, is printed once.
     *
     * @param list<Diagnostic> $errors
     * @param list<Diagnostic> $warnings
     */
    public static function diagnosticsToText(array $errors, array $warnings, string $cwd): string
    {
        $prefixed = [];
        foreach ($errors as $error) {
            $prefixed[] = [$error, ''];
        }
        foreach ($warnings as $warning) {
            $prefixed[] = [$warning, 'warning: '];
        }
        usort($prefixed, static fn(array $a, array $b): int => self::compareDiagnostics($a[0], $b[0]));
        $lines = [];
        foreach ($prefixed as [$diagnostic, $prefix]) {
            $lines[] = self::diagnosticToLine($diagnostic, $prefix, $cwd);
        }

        return implode('', array_unique($lines));
    }

    public static function diagnosticToLine(Diagnostic $diagnostic, string $prefix, string $cwd): string
    {
        if ($diagnostic->location === null) {
            return $prefix . $diagnostic->message . "\n";
        }
        $place = PathToShown::pathToShown($diagnostic->location->file, $cwd);
        if ($diagnostic->location->line !== null) {
            $place .= ':' . $diagnostic->location->line;
        }

        return sprintf("%s %s%s\n", $place, $prefix, $diagnostic->message);
    }

    private static function compareDiagnostics(Diagnostic $a, Diagnostic $b): int
    {
        $fileOrder = strcmp($a->location->file ?? '', $b->location->file ?? '');
        if ($fileOrder !== 0) {
            return $fileOrder;
        }

        return ($a->location->line ?? 0) <=> ($b->location->line ?? 0);
    }
}
