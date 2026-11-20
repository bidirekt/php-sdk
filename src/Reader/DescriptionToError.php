<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * The broker's rule for a field's description, which `#[BidirektField]` and `@bidirekt field` share.
 */
final class DescriptionToError
{
    private const WORDS_PATTERN = '/^[A-Za-z0-9]+(?: [A-Za-z0-9]+)*$/D';

    /**
     * Why the description breaks the rule, or null when it keeps it. The message shows the description as a JSON
     * string, so a line break or a quote in it reads as one.
     */
    public static function descriptionToError(string $description): ?string
    {
        if ($description === '') {
            return '"description" is empty: write a description or leave "description" out';
        }
        if (preg_match(self::WORDS_PATTERN, $description) === 1) {
            return null;
        }
        $shown = (string) json_encode($description, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_INVALID_UTF8_SUBSTITUTE);

        return sprintf('description %s must be ASCII letters and digits, with single spaces between words: remove or replace the other characters', $shown);
    }
}
