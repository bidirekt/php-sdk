<?php

/** @bidirekt schema */
final class Card
{
    /** @bidirekt field */
    public string $last4;
}

/** @bidirekt schema */
final class Pix
{
    /** @bidirekt field */
    public string $key;
}

/** @bidirekt schema */
final class Order
{
    /** @bidirekt field union=card:Card,pix:Pix */
    public Card|Pix $oldForm;

    /** @bidirekt field union={card: Card, pix: Pix,} */
    public Card|Pix $trailingComma;

    /** @bidirekt field union={} */
    public Card|Pix $emptyBraces;

    /** @bidirekt field union={card Card, pix: Pix} */
    public Card|Pix $missingColon;

    /** @bidirekt field union={card: Card, card: Pix} */
    public Card|Pix $repeatedKey;

    /** @bidirekt field union={card: Card, pix: Pix */
    public Card|Pix $unclosedBrace;

    /** @bidirekt field union=card: Card, pix: Pix} */
    public Card|Pix $unopenedBrace;

    /** @bidirekt field union={card: Card, pix: Pix}} */
    public Card|Pix $extraClosingBrace;

    /** @bidirekt field union={{card: Card, pix: Pix} */
    public Card|Pix $nestedBraces;
}
