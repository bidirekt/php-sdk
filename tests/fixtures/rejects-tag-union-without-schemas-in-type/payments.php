<?php

/** @bidirekt schema */
final class Card
{
    /** @bidirekt field */
    public string $last4;
}

/** @bidirekt schema */
final class Order
{
    /** @bidirekt field union={card: Card} */
    public string $label;
}
