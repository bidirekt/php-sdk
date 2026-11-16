<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;

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
    public function expressionToValue(Expr $expression, ParsedFile $file): ConstantValue|array
    {
        return $this->expressionToValueResolving($expression, $file, []);
    }

    /**
     * Names the arguments after the parameters, in their order when positional, and evaluates each.
     *
     * @param array<Node> $arguments
     * @param list<string> $parameters
     * @param string $what how the message names the attribute, like "#[BidirektField]"
     * @return AttributeArguments|list<Diagnostic>
     */
    public function argumentsToNamed(array $arguments, array $parameters, string $what, SourceLocation $location, ParsedFile $file): AttributeArguments|array
    {
        return $this->argumentsToNamedResolving($arguments, $parameters, $what, $location, $file, []);
    }

    /**
     * @param array<Node> $arguments
     * @param list<string> $parameters
     * @param array<string, true> $resolving the constants being evaluated, to stop at one that refers to itself
     * @return AttributeArguments|list<Diagnostic>
     */
    private function argumentsToNamedResolving(array $arguments, array $parameters, string $what, SourceLocation $location, ParsedFile $file, array $resolving): AttributeArguments|array
    {
        $values = [];
        $errors = [];
        $named = false;
        foreach (array_values($arguments) as $position => $argument) {
            if (!$argument instanceof Arg || $argument->unpack || $argument->byRef) {
                $errors[] = new Diagnostic(sprintf('cannot read this argument of %s: write each argument in place', $what), $file->locationOf($argument));

                continue;
            }
            $name = $this->argumentToName($argument, $position, $named, $parameters, $what, $file);
            $named = $named || $argument->name !== null;
            if ($name instanceof Diagnostic) {
                $errors[] = $name;

                continue;
            }
            if (isset($values[$name])) {
                $errors[] = new Diagnostic(sprintf('"%s" is passed more than once to %s: keep one', $name, $what), $file->locationOf($argument));

                continue;
            }
            $value = $this->expressionToValueResolving($argument->value, $file, $resolving);
            if (is_array($value)) {
                array_push($errors, ...$value);

                continue;
            }
            if ($value->value === null && !in_array($name, self::NULL_IS_VALUE, true)) {
                continue;
            }
            $values[$name] = $value;
        }
        if ($errors !== []) {
            return $errors;
        }

        return new AttributeArguments($values, $location);
    }

    /**
     * @param list<string> $parameters
     */
    private function argumentToName(Arg $argument, int $position, bool $afterNamed, array $parameters, string $what, ParsedFile $file): string|Diagnostic
    {
        if ($argument->name !== null) {
            $name = $argument->name->toString();
            if (!in_array($name, $parameters, true)) {
                return new Diagnostic(
                    sprintf('unknown argument "%s" in %s: write %s', $name, $what, NamesToChoice::namesToChoice($parameters)),
                    $file->locationOf($argument->name),
                );
            }

            return $name;
        }
        if ($afterNamed) {
            return new Diagnostic(sprintf('a positional argument follows a named one in %s: name it', $what), $file->locationOf($argument));
        }
        if (!isset($parameters[$position])) {
            return new Diagnostic(
                sprintf('%s takes at most %d arguments: remove the extra ones', $what, count($parameters)),
                $file->locationOf($argument),
            );
        }

        return $parameters[$position];
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function expressionToValueResolving(Expr $expression, ParsedFile $file, array $resolving): ConstantValue|array
    {
        $location = $file->locationOf($expression);
        if ($expression instanceof Int_) {
            return new ConstantValue($expression->value, $location, self::intToLiteral($expression));
        }
        if ($expression instanceof String_ || $expression instanceof Float_) {
            return new ConstantValue($expression->value, $location);
        }
        if ($expression instanceof ConstFetch && in_array($expression->name->toLowerString(), ['true', 'false', 'null'], true)) {
            return new ConstantValue(constant($expression->name->toLowerString()), $location);
        }
        if ($expression instanceof UnaryMinus || $expression instanceof UnaryPlus) {
            return $this->signedToValue($expression, $file, $resolving);
        }
        if ($expression instanceof Array_) {
            return $this->arrayToValue($expression, $file, $resolving);
        }
        if ($expression instanceof ClassConstFetch) {
            return $this->classConstantToValue($expression, $file, $resolving);
        }
        if ($expression instanceof PropertyFetch && $expression->var instanceof ClassConstFetch && $expression->name instanceof Identifier && $expression->name->toString() === 'value') {
            return $this->enumCaseValueToValue($expression->var, $file, $resolving);
        }
        if ($expression instanceof New_ && $expression->class instanceof Name && strtolower($expression->class->toString()) === strtolower(BidirektField::class)) {
            $arguments = $this->argumentsToNamedResolving($expression->args, SdkAttributes::classToParameters(BidirektField::class), 'new BidirektField(…)', $location, $file, $resolving);
            if (is_array($arguments)) {
                return $arguments;
            }

            return new ConstantValue(new FieldConstruction($arguments), $location);
        }

        return [self::unreadable($expression, $file)];
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function signedToValue(UnaryMinus|UnaryPlus $expression, ParsedFile $file, array $resolving): ConstantValue|array
    {
        $operand = $this->expressionToValueResolving($expression->expr, $file, $resolving);
        if (is_array($operand)) {
            return $operand;
        }
        if (!is_int($operand->value) && !is_float($operand->value)) {
            return [self::unreadable($expression, $file)];
        }
        $value = $operand->value;
        if ($expression instanceof UnaryMinus) {
            $value = -$value;
        }

        return new ConstantValue($value, $file->locationOf($expression));
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function arrayToValue(Array_ $array, ParsedFile $file, array $resolving): ConstantValue|array
    {
        $items = [];
        $errors = [];
        foreach ($array->items as $item) {
            if ($item->unpack || $item->byRef) {
                $errors[] = self::unreadable($item, $file);

                continue;
            }
            $key = null;
            if ($item->key !== null) {
                $key = $this->expressionToValueResolving($item->key, $file, $resolving);
                if (is_array($key)) {
                    array_push($errors, ...$key);

                    continue;
                }
            }
            $value = $this->expressionToValueResolving($item->value, $file, $resolving);
            if (is_array($value)) {
                array_push($errors, ...$value);

                continue;
            }
            $items[] = new ConstantArrayItem($key, $value);
        }
        if ($errors !== []) {
            return $errors;
        }

        return new ConstantValue(new ConstantArray($items), $file->locationOf($array));
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function classConstantToValue(ClassConstFetch $fetch, ParsedFile $file, array $resolving): ConstantValue|array
    {
        $location = $file->locationOf($fetch);
        if (!$fetch->name instanceof Identifier) {
            return [self::unreadable($fetch, $file)];
        }
        $class = $this->classNameToFullyQualified($fetch, $file);
        if ($class instanceof Diagnostic) {
            return [$class];
        }
        if ($fetch->name->toLowerString() === 'class') {
            return new ConstantValue(new ClassReference($class), $location);
        }
        $constant = $fetch->name->toString();
        $shown = sprintf('%s::%s', SchemaNaming::classToShortName($class), $constant);
        $key = strtolower($class) . '::' . $constant;
        if (isset($resolving[$key])) {
            return [new Diagnostic(sprintf('constant "%s" refers to itself: give it a value', $shown), $location)];
        }
        $declaration = $this->classToDeclaration($class, $location, sprintf('constant "%s"', $shown));
        if (!$declaration instanceof ClassDeclaration) {
            return $declaration;
        }
        $found = $this->declarationToConstant($declaration, $constant, []);
        if (is_array($found)) {
            return $found;
        }
        if ($found === null) {
            return [self::missingConstant($declaration, $constant, $shown, $location)];
        }
        $value = $this->withScope(null)->expressionToValueResolving($found->value, $found->owner->file, $resolving + [$key => true]);
        if (is_array($value)) {
            return $value;
        }

        return new ConstantValue($value->value, $location, $value->intLiteral);
    }

    /**
     * @param array<string, true> $resolving
     * @return ConstantValue|list<Diagnostic>
     */
    private function enumCaseValueToValue(ClassConstFetch $fetch, ParsedFile $file, array $resolving): ConstantValue|array
    {
        $location = $file->locationOf($fetch);
        if (!$fetch->name instanceof Identifier) {
            return [self::unreadable($fetch, $file)];
        }
        $class = $this->classNameToFullyQualified($fetch, $file);
        if ($class instanceof Diagnostic) {
            return [$class];
        }
        $shown = sprintf('%s::%s', SchemaNaming::classToShortName($class), $fetch->name->toString());
        $declaration = $this->classToDeclaration($class, $location, sprintf('enum case "%s"', $shown));
        if (!$declaration instanceof ClassDeclaration) {
            return $declaration;
        }
        $case = self::enumToCase($declaration->node, $fetch->name->toString());
        if ($case === null) {
            return [new Diagnostic(
                sprintf('enum case "%s" is not declared: write a case of enum "%s"', $shown, SchemaNaming::classToShortName($class)),
                $location,
            )];
        }
        if ($case->expr === null) {
            return [new Diagnostic(
                sprintf('enum "%s" has no values, so "%s->value" cannot be read: give its cases values', SchemaNaming::classToShortName($class), $shown),
                $location,
            )];
        }
        $value = $this->withScope(null)->expressionToValueResolving($case->expr, $declaration->file, $resolving);
        if (is_array($value)) {
            return $value;
        }

        return new ConstantValue($value->value, $location, $value->intLiteral);
    }

    private function classNameToFullyQualified(ClassConstFetch $fetch, ParsedFile $file): string|Diagnostic
    {
        if (!$fetch->class instanceof Name) {
            return self::unreadable($fetch, $file);
        }
        if (!$fetch->class->isSpecialClassName()) {
            return $fetch->class->toString();
        }
        $enclosing = NodeToEnclosingClass::nodeToEnclosingClass($fetch);
        if ($this->scope !== null) {
            $enclosing = $this->scope->selfClass->node;
        }
        if ($this->scope !== null && $fetch->class->toLowerString() === 'static') {
            $enclosing = $this->scope->staticClass->node;
        }
        if ($enclosing === null || $enclosing->namespacedName === null) {
            return self::unreadable($fetch, $file);
        }
        if ($fetch->class->toLowerString() !== 'parent') {
            return $enclosing->namespacedName->toString();
        }
        if (!$enclosing instanceof Class_ || $enclosing->extends === null) {
            return new Diagnostic(
                sprintf('"%s" has no parent: write the class that declares it', $file->textOf($fetch)),
                $file->locationOf($fetch),
            );
        }

        return $enclosing->extends->toString();
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
                sprintf('cannot find class "%s" of %s: declare it in the project or require its package with composer', $class, $what),
                $location,
            )];
        }

        return $lookup->declaration;
    }

    /**
     * The constant's expression and the class that declares it, searched in the class, its traits,
     * its parent and its interfaces, as PHP does.
     *
     * @param array<string, true> $visited node keys
     * @return ClassConstant|list<Diagnostic>|null
     */
    private function declarationToConstant(ClassDeclaration $declaration, string $constant, array $visited): ClassConstant|array|null
    {
        $id = $declaration->file->nodeToKey($declaration->node);
        if (isset($visited[$id])) {
            return null;
        }
        $visited[$id] = true;
        $location = $declaration->file->locationOf($declaration->node);
        $related = [];
        foreach ($declaration->node->stmts as $statement) {
            if ($statement instanceof ClassConst) {
                foreach ($statement->consts as $const) {
                    if ($const->name->toString() === $constant) {
                        return new ClassConstant($const->value, $declaration);
                    }
                }
            }
            if ($statement instanceof TraitUse) {
                array_push($related, ...$statement->traits);
            }
        }
        $node = $declaration->node;
        if ($node instanceof Class_ && $node->extends !== null) {
            $related[] = $node->extends;
        }
        if ($node instanceof Class_ || $node instanceof Enum_) {
            array_push($related, ...$node->implements);
        }
        if ($node instanceof Interface_) {
            array_push($related, ...$node->extends);
        }
        foreach ($related as $name) {
            $lookup = $this->classes->lookup($name->toString(), $location);
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
        if (self::enumToCase($declaration->node, $constant) !== null) {
            return new Diagnostic(sprintf('"%s" is an enum case, not a value: write %s->value', $shown, $shown), $location);
        }

        return new Diagnostic(
            sprintf('constant "%s" is not declared: declare it in %s or one of its parents', $shown, SchemaClasses::nodeToDescription($declaration->node)),
            $location,
        );
    }

    private static function enumToCase(ClassLike $node, string $name): ?EnumCase
    {
        if (!$node instanceof Enum_) {
            return null;
        }
        foreach ($node->stmts as $statement) {
            if ($statement instanceof EnumCase && $statement->name->toString() === $name) {
                return $statement;
            }
        }

        return null;
    }

    private static function intToLiteral(Int_ $literal): ?string
    {
        $raw = $literal->getAttribute('rawValue');
        if (!is_string($raw)) {
            return null;
        }

        return $raw;
    }

    private static function unreadable(Node $expression, ParsedFile $file): Diagnostic
    {
        return new Diagnostic(
            sprintf(
                '"%s" cannot be read without running the code: write a literal, an array, X::class, a class constant or Enum::Case->value',
                $file->textOf($expression),
            ),
            $file->locationOf($expression),
        );
    }
}
