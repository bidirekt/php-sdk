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
    public function __construct(
        /** @bidirekt field */
        public string $key,
    ) {}
}

/** @bidirekt schema */
final class Order
{
    /**
     * @bidirekt field union={card: Card, pix: Pix}
     * @var list<Card|Pix>
     */
    public array $history;

    /**
     * @bidirekt field union={card:Card,pix:Pix}
     * @var array<string, Card|Pix|null>
     */
    public array $byId;
}
