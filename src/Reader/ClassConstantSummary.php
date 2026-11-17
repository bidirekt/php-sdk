<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * `X::NAME` or `X::class`, with the class-like it is written in, which `self`, `static` and `parent` name
 * when no schema scope replaces it.
 */
final class ClassConstantSummary extends ExpressionSummary
{
    /**
     * @param string $class the resolved name, or `self`, `static` or `parent` as written
     * @param string|null $special `self`, `static` or `parent` in lowercase, null for any other class
     * @param string|null $enclosingClass the name of the class-like it is written in, null in none or in an anonymous class
     * @param bool $enclosingIsClass whether that class-like is a class, which alone has a parent
     * @param string|null $enclosingParent that class's parent
     */
    public function __construct(
        SourceLocation $location,
        public string $text,
        public string $class,
        public ?string $special,
        public string $constant,
        public ?string $enclosingClass,
        public bool $enclosingIsClass,
        public ?string $enclosingParent,
    ) {
        parent::__construct($location);
    }
}
