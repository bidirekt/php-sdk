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

    #[BidirektField(name: '~')]
    public string $looksLikeTildeNull;

    #[BidirektField(description: '123')]
    public string $looksLikeInteger;

    #[BidirektField(description: '0x1F')]
    public string $looksLikeHexadecimal;

    #[BidirektField(name: '2001-12-14')]
    public string $looksLikeDate;

    #[BidirektField(name: 'a: b')]
    public string $colonAndSpace;

    #[BidirektField(name: 'a:b')]
    public string $colonWithoutSpace;

    #[BidirektField(name: 'a #b')]
    public string $spaceAndHash;

    #[BidirektField(name: 'a#b')]
    public string $hashWithoutSpace;

    #[BidirektField(name: '#x')]
    public string $startsWithHash;

    #[BidirektField(name: '*x')]
    public string $startsWithAsterisk;

    #[BidirektField(name: '&x')]
    public string $startsWithAmpersand;

    #[BidirektField(name: '!x')]
    public string $startsWithExclamationMark;

    #[BidirektField(name: '|x')]
    public string $startsWithPipe;

    #[BidirektField(name: '>x')]
    public string $startsWithGreaterThan;

    #[BidirektField(name: '\'x')]
    public string $startsWithSingleQuote;

    #[BidirektField(name: '"x')]
    public string $startsWithDoubleQuote;

    #[BidirektField(name: '%x')]
    public string $startsWithPercent;

    #[BidirektField(name: '@x')]
    public string $startsWithAt;

    #[BidirektField(name: '`x')]
    public string $startsWithBacktick;

    #[BidirektField(name: '- x')]
    public string $startsWithDashAndSpace;

    #[BidirektField(name: '? x')]
    public string $startsWithQuestionMarkAndSpace;

    #[BidirektField(name: ',x')]
    public string $startsWithComma;

    #[BidirektField(name: '-x')]
    public string $startsWithDash;

    #[BidirektField(name: '?x')]
    public string $startsWithQuestionMark;

    #[BidirektField(name: ':x')]
    public string $startsWithColon;

    #[BidirektField(name: ' x')]
    public string $leadingSpace;

    #[BidirektField(name: 'x ')]
    public string $trailingSpace;

    #[BidirektField(name: "it's")]
    public string $apostrophe;

    #[BidirektField(name: 'say "hi"')]
    public string $doubleQuotesInside;

    #[BidirektField(name: 'a\\b')]
    public string $backslash;
}
