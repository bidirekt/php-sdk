<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(description: 'plain text')]
    public string $plainText;

    #[BidirektField(description: 'yes')]
    public string $looksLikeYes;

    #[BidirektField(description: 'on')]
    public string $looksLikeOn;

    #[BidirektField(description: 'true')]
    public string $looksLikeTrue;

    #[BidirektField(description: 'null')]
    public string $looksLikeNull;

    #[BidirektField(description: '~')]
    public string $looksLikeTildeNull;

    #[BidirektField(description: '')]
    public string $empty;

    #[BidirektField(description: '123')]
    public string $looksLikeInteger;

    #[BidirektField(description: '1.5')]
    public string $looksLikeFloat;

    #[BidirektField(description: '0x1F')]
    public string $looksLikeHexadecimal;

    #[BidirektField(description: '.inf')]
    public string $looksLikeInfinity;

    #[BidirektField(description: '2001-12-14')]
    public string $looksLikeDate;

    #[BidirektField(description: 'a: b')]
    public string $colonAndSpace;

    #[BidirektField(description: 'a:b')]
    public string $colonWithoutSpace;

    #[BidirektField(description: 'a #b')]
    public string $spaceAndHash;

    #[BidirektField(description: 'a#b')]
    public string $hashWithoutSpace;

    #[BidirektField(description: '#x')]
    public string $startsWithHash;

    #[BidirektField(description: '*x')]
    public string $startsWithAsterisk;

    #[BidirektField(description: '&x')]
    public string $startsWithAmpersand;

    #[BidirektField(description: '!x')]
    public string $startsWithExclamationMark;

    #[BidirektField(description: '|x')]
    public string $startsWithPipe;

    #[BidirektField(description: '>x')]
    public string $startsWithGreaterThan;

    #[BidirektField(description: '\'x')]
    public string $startsWithSingleQuote;

    #[BidirektField(description: '"x')]
    public string $startsWithDoubleQuote;

    #[BidirektField(description: '%x')]
    public string $startsWithPercent;

    #[BidirektField(description: '@x')]
    public string $startsWithAt;

    #[BidirektField(description: '`x')]
    public string $startsWithBacktick;

    #[BidirektField(description: '- x')]
    public string $startsWithDashAndSpace;

    #[BidirektField(description: '? x')]
    public string $startsWithQuestionMarkAndSpace;

    #[BidirektField(description: '[x')]
    public string $startsWithOpeningBracket;

    #[BidirektField(description: ']x')]
    public string $startsWithClosingBracket;

    #[BidirektField(description: '{x')]
    public string $startsWithOpeningBrace;

    #[BidirektField(description: '}x')]
    public string $startsWithClosingBrace;

    #[BidirektField(description: ',x')]
    public string $startsWithComma;

    #[BidirektField(description: '-x')]
    public string $startsWithDash;

    #[BidirektField(description: '?x')]
    public string $startsWithQuestionMark;

    #[BidirektField(description: ':x')]
    public string $startsWithColon;

    #[BidirektField(description: ' x')]
    public string $leadingSpace;

    #[BidirektField(description: 'x ')]
    public string $trailingSpace;

    #[BidirektField(description: "it's")]
    public string $apostrophe;

    #[BidirektField(description: 'say "hi"')]
    public string $doubleQuotesInside;

    #[BidirektField(description: "a\nb")]
    public string $lineBreak;

    #[BidirektField(description: 'a\\b')]
    public string $backslash;

    #[BidirektField(description: "a\tb")]
    public string $tab;
}
