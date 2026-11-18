<?php

/** @bidirekt schema */
final class Card
{
    /** @bidirekt field */
    public string $number;
}

/** @bidirekt schema */
final class Pix
{
    /** @bidirekt field */
    public string $key;
}

/** @bidirekt schema */
final class Boleto
{
    /** @bidirekt field */
    public string $code;
}

/** @bidirekt schema */
final class Order
{
    /** @bidirekt field union={card: Card, boleto: Boleto} */
    public Card|Pix $payment;

    /** @bidirekt field union={card: Card} */
    public Card|Pix $refund;

    /** @bidirekt field union={card: Card, pix: Pix, again: Pix} */
    public Card|Pix $retry;
}
