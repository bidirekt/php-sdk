<?php

/** @bidirekt schema */
final class Card
{
    /** @bidirekt field */
    public string $last4;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field name="petName" */
    public string $name;

    /** @bidirekt field union={card: "Card"} */
    public Card $payment;

    /** @bidirekt field description=the"tag */
    public string $tag;

    /** @bidirekt field description="the pet's age" */
    public int $age;
}

/** @bidirekt schema name="Owner" */
final class Owner
{
    /** @bidirekt field */
    public string $name;
}
