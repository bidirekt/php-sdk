<?php

/** @bidirekt schema */
final class Toy
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field type=array items=integer */
    public array $scores;

    /** @bidirekt field type=map values=float */
    public array $weights;

    /** @bidirekt field type=array items=Toy */
    public $toys;

    /** @bidirekt field type=map values=string optional=true */
    public array $labels;

    /** @bidirekt field type=string */
    public DateTimeImmutable $bornAt;

    /**
     * @bidirekt field
     * @var list<list<int>>|null
     */
    public ?array $grid;

    /**
     * @bidirekt field
     * @var list<Toy>
     */
    public array $favorites;

    /**
     * @bidirekt field type=array items=Toy
     * @var Toy[]|null
     */
    public ?array $gifts;
}
