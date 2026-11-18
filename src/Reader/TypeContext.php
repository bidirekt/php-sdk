<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Model\UnionNode;

/**
 * What translating one field's type needs besides the type itself.
 */
final class TypeContext
{
    /**
     * @param UnionNode|null $union the declared `union`, placed at the union of classes the type writes
     * @param ClassScope $scope the classes `self` and `static` name
     * @param SourceLocation $location the field's, where every error points
     * @param AnnotationForm $form the field's annotation, which the fixes are written in
     * @param string $docblock how messages say where the docblock that types the field is written
     */
    public function __construct(
        public WrittenType $written,
        public ?UnionNode $union,
        public ClassScope $scope,
        public SourceLocation $location,
        public AnnotationForm $form,
        public string $docblock,
    ) {}
}
