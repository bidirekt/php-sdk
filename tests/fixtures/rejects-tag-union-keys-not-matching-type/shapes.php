<?php

/** @bidirekt schema */
final class Receipt
{
    /** @bidirekt field union={card: Card, boleto: Boleto} */
    public Card|Pix $payment;
}

/** @bidirekt schema */
final class CardInfo
{
    /** @bidirekt field */
    public string $number;
}

/** @bidirekt schema */
final class PixInfo
{
    /** @bidirekt field */
    public string $key;
}

/** @bidirekt schema */
final class Invoice
{
    /**
     * @bidirekt field union={card: CardInfo}
     * @var list<CardInfo|PixInfo>
     */
    public array $payments;
}
