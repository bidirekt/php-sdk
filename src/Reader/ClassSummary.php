<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;

/**
 * What the reader reads of a class, interface, trait or enum once its file's tree is gone.
 */
final class ClassSummary
{
    /**
     * @param string $key the node key in its file, which its `@bidirekt schema` tag is placed by
     * @param string|null $name the namespaced name, null for an anonymous class
     * @param string $kind `class`, `interface`, `trait` or `enum`
     * @param string $description how messages name it: `class "Pet"`, `an anonymous class`…
     * @param NameSummary|null $parent a class's `extends`
     * @param list<NameSummary> $interfaces what a class or an enum implements, or an interface extends
     * @param string|null $scalarType an enum's backing type, in lowercase
     * @param list<SdkAttribute> $attributes
     * @param list<TraitUseSummary|MemberDeclaration> $members in the order they are written
     * @param array<string, ExpressionSummary> $constants name => value, the first declared
     * @param list<EnumCaseSummary> $cases
     * @param bool $unimportedSchema whether it has a #[BidirektSchema] without its `use`, an error of its own
     */
    public function __construct(
        public string $key,
        public ?string $name,
        public string $kind,
        public string $description,
        public SourceLocation $location,
        public ?NameSummary $parent,
        public array $interfaces,
        public ?string $scalarType,
        public array $attributes,
        public array $members,
        public array $constants,
        public array $cases,
        public bool $unimportedSchema,
    ) {}

    public function caseNamed(string $name): ?EnumCaseSummary
    {
        foreach ($this->cases as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return null;
    }
}
