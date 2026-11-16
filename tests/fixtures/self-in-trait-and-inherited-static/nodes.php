<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

trait HasParent
{
    #[BidirektField]
    public ?self $parent;

    #[BidirektField(type: self::class, name: self::SIBLING)]
    public $next;

    /** @var list<self> */
    #[BidirektField]
    public array $children;
}

#[BidirektSchema]
final class Folder
{
    use HasParent;

    public const SIBLING = 'nextFolder';

    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Tag
{
    use HasParent;

    public const SIBLING = 'nextTag';

    #[BidirektField]
    public string $label;
}

#[BidirektSchema]
class Pet
{
    /** @var static|null */
    #[BidirektField]
    public $mother;

    #[BidirektField]
    public ?self $owner;
}

#[BidirektSchema]
final class Dog extends Pet
{
    #[BidirektField]
    public string $breed;
}
