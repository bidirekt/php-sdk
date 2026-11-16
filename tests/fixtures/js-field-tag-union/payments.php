<?php

/** @bidirekt schema */
class CardPayment
{
    /**
     * @bidirekt field
     * @var string
     */
    public $last4;
}

/** @bidirekt schema name=Pix */
class PixPayment
{
    /**
     * @bidirekt field
     * @var string
     */
    public $key;
}

/** @bidirekt schema */
class Order
{
    /**
     * @bidirekt field union={card: CardPayment, pix: Pix}
     * @var CardPayment|PixPayment|null
     */
    public $payment;

    /** @bidirekt field union={card:CardPayment} optional=true */
    public $refund;
}
