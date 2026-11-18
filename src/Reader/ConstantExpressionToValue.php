<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use LogicException;

/**
 * Evaluates the expressions PHP allows in an attribute without running anything: literals, arrays,
 * `X::class`, class constants (found through the class index, the vendor folder included),
 * `Enum::Case->value` and the `new BidirektField(…)` of `fields:`.
 */
final class ConstantExpressionToValue
{
    /** `request: null` declares an empty body; any other parameter passed null takes its default, as PHP does */
    private const NULL_IS_VALUE = ['request'];

    /** the classes `self`, `static` and `parent` name in the expressions of a field read for a schema */
    private ?ClassScope $scope = null;

    public function __construct(
        private ClassIndex $classes,
    ) {}

    public function withScope(?ClassScope $scope): self
    {
        $scoped = clone $this;
        $scoped->scope = $scope;

        return $scoped;
    }

    /**
     * @return ConstantValue|list<Diagnostic>
     */
    public function expressionToValue(ExpressionSummary $expression): ConstantValue|array
    {
        return $this->expressionToValueResolving($expression, []);
    }

    /**
     * Names the arguments after the parameters, in their order when positional, and evaluates each.
     *
     * @param list<ArgumentSummary> $arguments
     * @param list<string> $parameters
     * @param string $what how the message names the attribute, like "#[BidirektField]"
     * @return AttributeArguments|list<Diagnostic>
     */
    public function argumentsToNamed(array $arguments, array $parameters, string $what, SourceLocation $location): AttributeArguments|array
    {
        $named = $this->argumentsToNamedAndErrors($arguments, $parameters, $what, $location);
        if ($named->errors !== []) {
            return $named->errors;
        }

        return $named;
    }

    /**
     * Like argumentsToNamed(), keeping the arguments that can be read beside the errors of the others.
     *
     * @param list<ArgumentSummary> $arguments
     * @param list<string> $parameters
     */
    public function argumentsToNamedAndErrors(array $arguments, array $parameters, string $what, SourceLocation $location): AttributeArguments
    {
        return $this->argumentsToNamedResolving($arguments, $parameters, $what, $location, []);
    }

    /**
     * @param list<ArgumentSummary> $arguments
     * @param list<string> $parameters
     * @param array<string, true> $resolving the constants being evaluated, to stop at one that refers to itself
     */
    private function argumentsToNamedResolving(array $arguments, array $parameters, string $what, SourceLocation $location, array $resolving): AttributeArguments
    {
        $values = [];
        $errors = [];
        $named = false;
        foreach ($arguments as $position => $argument) {
            if ($argument->value === null) {
                $errors[] = new Diagnostic(sprintf('cannot read this argument of %s: write each argument in place', $what), $argument->location);

                continue;
            }
            $name = self::argumentToName($argument, $position, $named, $parameters, $what);
            $named = $named || $argument->name !== null;
            if ($name instanceof Diagnostic) {
                $errors[] = $name;

                continue;
            }
            if (isset($values[$name])) {
                $errors[] = new Diagnostic(sprintf('"%s" is passed more than once to %s: keep one', $name, $what), $argument->location);

                continue;
            }
            $value = $this->expressionToValueResolving($argument->value, $resolving);
            if (is_array($value)) {
                array_push($errors, ...$value);

                continue;
            }
            if ($value->value === null && !in_array($name, self::NULL_IS_VALUE, true)) {
                continue;
            }
            $values[$name] = $value;
        }

        return new AttributeArguments($values, $location, $errors);
    }

    /**
     * @param list<string> $parameters
     */
    private static function argumentToName(ArgumentSummary $argument, int $position, bool $afterNamed, array $parameters, string $what): string|Diagnostic
    {
        if ($argument->name !== null) {
            if (!in_array($argument->name, $parameters, true)) {
                return new Diagnostic(
                    sprintf('unknown argument "%s" in %s: write %s', $argument->name, $what, NamesToChoice::namesToChoice($parameters)),
                    $argument->nameLocation ?? $argument->location,
                );
            }

            return $argument->name;
        }
        if ($afterNamed) {
            return new Diagnostic(sprintf('a positional argument follows a named one in %s: name it', $what), $argument->location);
        }
        if (!isset($parameters[$position])) {
            return new Diagnostic(
                sprintf('%s takes at most %d arguments: remove the extra ones', $what, count($parameters)),
                $argument->location,
            );
        }

        return $parameters[$position];
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function expressionToValueResolving(ExpressionSummary $expression, array $resolving): ConstantValue|array
    {
        if ($expression instanceof LiteralSummary) {
            return new ConstantValue($expression->value, $expression->location, $expression->intLiteral);
        }
        if ($expression instanceof SignedSummary) {
            return $this->signedToValue($expression, $resolving);
        }
        if ($expression instanceof ArraySummary) {
            return $this->arrayToValue($expression, $resolving);
        }
        if ($expression instanceof ClassConstantSummary) {
            return $this->classConstantToValue($expression, $resolving);
        }
        if ($expression instanceof EnumValueSummary) {
            return $this->enumCaseValueToValue($expression->case, $resolving);
        }
        if ($expression instanceof NewFieldSummary) {
            $arguments = $this->argumentsToNamedResolving($expression->arguments, SdkAttributes::classToParameters(BidirektField::class), 'new BidirektField(…)', $expression->location, $resolving);
            if ($arguments->errors !== []) {
                return $arguments->errors;
            }

            return new ConstantValue(new FieldConstruction($arguments), $expression->location);
        }
        if ($expression instanceof UnimportedFieldSummary) {
            return [new Diagnostic(
                sprintf('new %s(…) resolves to "%s", so it is not read: write %s', $expression->written, $expression->class, $expression->fix),
                $expression->location,
            )];
        }
        if ($expression instanceof UnreadableSummary) {
            return [self::unreadable($expression->text, $expression->location)];
        }

        throw new LogicException(sprintf('no evaluation for %s', $expression::class));
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function signedToValue(SignedSummary $expression, array $resolving): ConstantValue|array
    {
        $operand = $this->expressionToValueResolving($expression->operand, $resolving);
        if (is_array($operand)) {
            return $operand;
        }
        if (!is_int($operand->value) && !is_float($operand->value)) {
            return [self::unreadable($expression->text, $expression->location)];
        }
        $value = $operand->value;
        if ($expression->negative) {
            $value = -$value;
        }

        return new ConstantValue($value, $expression->location);
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function arrayToValue(ArraySummary $array, array $resolving): ConstantValue|array
    {
        $items = [];
        $errors = [];
        foreach ($array->items as $item) {
            $key = null;
            if ($item->key !== null) {
                $key = $this->expressionToValueResolving($item->key, $resolving);
                if (is_array($key)) {
                    array_push($errors, ...$key);

                    continue;
                }
            }
            $value = $this->expressionToValueResolving($item->value, $resolving);
            if (is_array($value)) {
                array_push($errors, ...$value);

                continue;
            }
            $items[] = new ConstantArrayItem($key, $value);
        }
        if ($errors !== []) {
            return $errors;
        }

        return new ConstantValue(new ConstantArray($items), $array->location);
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function classConstantToValue(ClassConstantSummary $fetch, array $resolving): ConstantValue|array
    {
        $location = $fetch->location;
        $class = $this->fetchToClass($fetch);
        if ($class instanceof Diagnostic) {
            return [$class];
        }
        $constant = $fetch->constant;
        if (strtolower($constant) === 'class') {
            if ($class instanceof ClassDeclaration) {
                return [self::unreadable($fetch->text, $location)];
            }

            return new ConstantValue(new ClassReference($class), $location);
        }
        if (is_string($class)) {
            $shown = sprintf('%s::%s', SchemaNaming::classToShortName($class), $constant);
            $key = strtolower($class) . '::' . $constant;
        } else {
            $shown = $fetch->text;
            $key = spl_object_id($class->summary) . '::' . $constant;
        }
        if (isset($resolving[$key])) {
            return [new Diagnostic(sprintf('constant "%s" refers to itself: give it a value', $shown), $location)];
        }
        $declaration = $class;
        if (is_string($declaration)) {
            $declaration = $this->classToDeclaration($declaration, $location, sprintf('constant "%s"', $fetch->text));
            if (!$declaration instanceof ClassDeclaration) {
                return $declaration;
            }
        }
        $found = $this->declarationToConstant($declaration, $constant, []);
        if (is_array($found)) {
            return $found;
        }
        if ($found === null) {
            return [self::missingConstant($declaration, $constant, $shown, $location)];
        }
        $value = $this->withScope(null)->expressionToValueResolving($found->value, $resolving + [$key => true]);
        if (is_array($value)) {
            return $value;
        }

        return new ConstantValue($value->value, $location, $value->intLiteral);
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function enumCaseValueToValue(ClassConstantSummary $fetch, array $resolving): ConstantValue|array
    {
        $location = $fetch->location;
        $class = $this->fetchToClass($fetch);
        if ($class instanceof Diagnostic) {
            return [$class];
        }
        if ($class instanceof ClassDeclaration) {
            return [self::unreadable($fetch->text, $location)];
        }
        $shown = sprintf('%s::%s', SchemaNaming::classToShortName($class), $fetch->constant);
        $key = strtolower($class) . '::' . $fetch->constant . '->value';
        if (isset($resolving[$key])) {
            return [new Diagnostic(sprintf('enum case "%s" refers to itself: give it a value', $shown), $location)];
        }
        $declaration = $this->classToDeclaration($class, $location, sprintf('enum case "%s"', $fetch->text));
        if (!$declaration instanceof ClassDeclaration) {
            return $declaration;
        }
        $case = self::enumToCase($declaration->summary, $fetch->constant);
        if ($case === null) {
            return [new Diagnostic(
                sprintf('enum case "%s" is not declared: write a case of enum "%s"', $shown, SchemaNaming::classToShortName($class)),
                $location,
            )];
        }
        if ($case->value === null) {
            return [new Diagnostic(
                sprintf('enum "%s" has no values, so "%s->value" cannot be read: give its cases values', SchemaNaming::classToShortName($class), $shown),
                $location,
            )];
        }
        $value = $this->withScope(null)->expressionToValueResolving($case->value, $resolving + [$key => true]);
        if (is_array($value)) {
            return $value;
        }

        return new ConstantValue($value->value, $location, $value->intLiteral);
    }

    /**
     * The class the fetch names: `self`, `static` and `parent` stand for the class it is written in, or for
     * the scope's classes when one is set; an anonymous class, which no name finds, is its declaration.
     */
    private function fetchToClass(ClassConstantSummary $fetch): string|ClassDeclaration|Diagnostic
    {
        if ($fetch->special === null) {
            return $fetch->class;
        }
        $name = $fetch->enclosingClass;
        $isClass = $fetch->enclosingIsClass;
        $parent = $fetch->enclosingParent;
        $enclosing = null;
        if ($this->scope !== null) {
            $enclosing = $this->scope->selfClass;
            if ($fetch->special === 'static') {
                $enclosing = $this->scope->staticClass;
            }
            $name = $enclosing->summary->name;
            $isClass = $enclosing->summary->kind === 'class';
            $parent = $enclosing->summary->parent?->resolved;
        }
        if ($fetch->special === 'parent') {
            if (!$isClass || $parent === null) {
                return new Diagnostic(sprintf('"%s" has no parent: write the class that declares it', $fetch->text), $fetch->location);
            }

            return $parent;
        }
        if ($name !== null) {
            return $name;
        }

        return $enclosing ?? self::unreadable($fetch->text, $fetch->location);
    }

    /**
     * @param string $what what the class is needed for, like `constant "Pet::NAME"`
     * @return ClassDeclaration|list<Diagnostic>
     */
    private function classToDeclaration(string $class, SourceLocation $location, string $what): ClassDeclaration|array
    {
        $lookup = $this->classes->lookup($class, $location);
        if ($lookup->errors !== []) {
            return $lookup->errors;
        }
        if ($lookup->declaration === null) {
            return [new Diagnostic(
                sprintf('cannot find class "%s" of %s: %s', $class, $what, $this->classes->missingClassFix()),
                $location,
            )];
        }

        return $lookup->declaration;
    }

    /**
     * The constant's expression and the class that declares it, searched in the class, its traits,
     * its parent and its interfaces, as PHP does.
     *
     * @param array<int, true> $visited summary ids
     * @return ClassConstant|list<Diagnostic>|null
     */
    private function declarationToConstant(ClassDeclaration $declaration, string $constant, array $visited): ClassConstant|array|null
    {
        $class = $declaration->summary;
        if (isset($visited[spl_object_id($class)])) {
            return null;
        }
        $visited[spl_object_id($class)] = true;
        if (isset($class->constants[$constant])) {
            return new ClassConstant($class->constants[$constant], $declaration);
        }
        $related = [];
        foreach ($class->members as $member) {
            if ($member instanceof TraitUseSummary) {
                array_push($related, ...$member->traits);
            }
        }
        if ($class->parent !== null) {
            $related[] = $class->parent;
        }
        array_push($related, ...$class->interfaces);
        foreach ($related as $name) {
            $lookup = $this->classes->lookup($name->resolved, $class->location);
            if ($lookup->errors !== []) {
                return $lookup->errors;
            }
            if ($lookup->declaration === null) {
                continue;
            }
            $found = $this->declarationToConstant($lookup->declaration, $constant, $visited);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private static function missingConstant(ClassDeclaration $declaration, string $constant, string $shown, SourceLocation $location): Diagnostic
    {
        if (self::enumToCase($declaration->summary, $constant) !== null) {
            return new Diagnostic(sprintf('"%s" is an enum case, not a value: write %s->value', $shown, $shown), $location);
        }

        return new Diagnostic(
            sprintf('constant "%s" is not declared: declare it in %s or one of its parents', $shown, $declaration->summary->description),
            $location,
        );
    }

    private static function enumToCase(ClassSummary $class, string $name): ?EnumCaseSummary
    {
        if ($class->kind !== 'enum') {
            return null;
        }

        return $class->caseNamed($name);
    }

    private static function unreadable(string $text, SourceLocation $location): Diagnostic
    {
        return new Diagnostic(
            sprintf(
                '"%s" cannot be read without running the code: write a literal, an array, X::class, a class constant or Enum::Case->value',
                $text,
            ),
            $location,
        );
    }
}
