<?php

/** @bidirekt schema */
final class CardPayment
{
    /** @bidirekt field type=string */
    public $number;
}

/** @bidirekt schema */
final class PixPayment
{
    /** @bidirekt field type=string */
    public $key;
}

final class Address
{
}

interface Located
{
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public $notes;

    /** @bidirekt field */
    public int|string $code;

    /** @bidirekt field */
    public CardPayment|PixPayment $payment;

    /** @bidirekt field */
    public Address $address;

    /**
     * @bidirekt field
     * @var array{street: string}
     */
    public array $location;

    /** @bidirekt field */
    public DateTimeImmutable $bornAt;

    /** @bidirekt field */
    public Located $place;

    /**
     * @bidirekt field
     * @var Collection<int, CardPayment>
     */
    public $payments;

    /** @bidirekt field */
    public mixed $extra;
}
