<?php

/** @bidirekt schema Pet */
class Pet
{
}

$anonymous = new /** @bidirekt schema */ class {
    /** @bidirekt field */
    public string $name;
};

/** @bidirekt schema */
class Dog
{
    /** @bidirekt field kind=integer */
    public $id;

    /** @bidirekt field integer */
    public $age;

    /** @bidirekt field type=map<integer */
    public $bornAt;

    /** @bidirekt field optional=yes */
    public $nickname;

    /** @bidirekt field nullable=1 */
    public $color;

    /** @bidirekt field name= */
    public $breed;

    /** @bidirekt field description="" */
    public $bio;

    /** @bidirekt field union=card */
    public $payment;

    /** @bidirekt field union={card: CardPayment, card: PixPayment} */
    public $refund;

    /** @bidirekt field name=a name=b */
    public $tag;

    /** @bidirekt field type=integer union={card: CardPayment} */
    public $owner;

    /**
     * @bidirekt field
     * @bidirekt field name=size_name
     * @var string
     */
    public $size;

    /** @bidirekt field union={card: Card} */
    public $coupon;

    /** @bidirekt field */
    public $notes;

    /** @bidirekt field type=Pet[] */
    public $pets;

    /** @bidirekt field type=map<string> */
    public $labels;

    /** @bidirekt field items=integer */
    public $scores;

    /** @bidirekt field type=map values=string items=string */
    public $names;

    /** @bidirekt field type=array */
    public $toys;

    /** @bidirekt field type=map */
    public $weights;

    /** @bidirekt field type=array items=Pet[] */
    public $litters;

    /** @bidirekt field type=map values=array */
    public $grid;

    /** @bidirekt field */
    public array $tags;
}
