<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektField;
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

/**
 * Keeps the expressions PHP allows in an attribute, a constant or an enum case as summaries, so they are
 * evaluated after the file's tree is gone; any other expression is kept as unreadable, with its text.
 */
final class ExpressionToSummary
{
    /**
     * @param array<Node> $arguments
     * @return list<ArgumentSummary>
     */
    public static function argumentsToSummaries(array $arguments, ParsedFile $file): array
    {
        $summaries = [];
        foreach ($arguments as $argument) {
            if (!$argument instanceof Arg || $argument->unpack || $argument->byRef) {
                $summaries[] = new ArgumentSummary(null, null, $file->locationOf($argument), null);

                continue;
            }
            $name = null;
            $nameLocation = null;
            if ($argument->name !== null) {
                $name = $argument->name->toString();
                $nameLocation = $file->locationOf($argument->name);
            }
            $summaries[] = new ArgumentSummary($name, $nameLocation, $file->locationOf($argument), self::expressionToSummary($argument->value, $file));
        }

        return $summaries;
    }

    public static function expressionToSummary(Expr $expression, ParsedFile $file): ExpressionSummary
    {
        $location = $file->locationOf($expression);
        if ($expression instanceof Int_) {
            return new LiteralSummary($location, $expression->value, self::intToLiteral($expression));
        }
        if ($expression instanceof String_ || $expression instanceof Float_) {
            return new LiteralSummary($location, $expression->value);
        }
        if ($expression instanceof ConstFetch && in_array($expression->name->toLowerString(), ['true', 'false', 'null'], true)) {
            return new LiteralSummary($location, constant($expression->name->toLowerString()));
        }
        if ($expression instanceof UnaryMinus || $expression instanceof UnaryPlus) {
            return new SignedSummary($location, $file->textOf($expression), $expression instanceof UnaryMinus, self::expressionToSummary($expression->expr, $file));
        }
        if ($expression instanceof Array_) {
            return self::arrayToSummary($expression, $file);
        }
        if ($expression instanceof ClassConstFetch) {
            return self::fetchToSummary($expression, $file);
        }
        if ($expression instanceof PropertyFetch && $expression->var instanceof ClassConstFetch && $expression->name instanceof Identifier && $expression->name->toString() === 'value') {
            $case = self::fetchToSummary($expression->var, $file);
            if (!$case instanceof ClassConstantSummary) {
                return $case;
            }

            return new EnumValueSummary($location, $case);
        }
        if ($expression instanceof New_ && $expression->class instanceof Name && strtolower($expression->class->toString()) === strtolower(BidirektField::class)) {
            return new NewFieldSummary($location, self::argumentsToSummaries($expression->args, $file));
        }
        if ($expression instanceof New_ && $expression->class instanceof Name && SdkAttributes::nameToUnimportedClass($expression->class, $expression) === BidirektField::class) {
            return new UnimportedFieldSummary($location, $expression->class->toString());
        }

        return new UnreadableSummary($location, $file->textOf($expression));
    }

    private static function arrayToSummary(Array_ $array, ParsedFile $file): ArraySummary
    {
        $items = [];
        foreach ($array->items as $item) {
            if ($item->unpack || $item->byRef) {
                $items[] = new ArrayItemSummary(null, new UnreadableSummary($file->locationOf($item), $file->textOf($item)));

                continue;
            }
            $key = null;
            if ($item->key !== null) {
                $key = self::expressionToSummary($item->key, $file);
            }
            $items[] = new ArrayItemSummary($key, self::expressionToSummary($item->value, $file));
        }

        return new ArraySummary($file->locationOf($array), $items);
    }

    private static function fetchToSummary(ClassConstFetch $fetch, ParsedFile $file): ClassConstantSummary|UnreadableSummary
    {
        $location = $file->locationOf($fetch);
        if (!$fetch->name instanceof Identifier || !$fetch->class instanceof Name) {
            return new UnreadableSummary($location, $file->textOf($fetch));
        }
        $special = null;
        $enclosingClass = null;
        $enclosingParent = null;
        $enclosingIsClass = false;
        if ($fetch->class->isSpecialClassName()) {
            $special = $fetch->class->toLowerString();
            $enclosing = NodeToEnclosingClass::nodeToEnclosingClass($fetch);
            $enclosingClass = $enclosing?->namespacedName?->toString();
            $enclosingIsClass = $enclosing instanceof Class_;
            if ($enclosing instanceof Class_) {
                $enclosingParent = $enclosing->extends?->toString();
            }
        }

        return new ClassConstantSummary(
            $location,
            $file->textOf($fetch),
            $fetch->class->toString(),
            $special,
            $fetch->name->toString(),
            $enclosingClass,
            $enclosingIsClass,
            $enclosingParent,
        );
    }

    private static function intToLiteral(Int_ $literal): ?string
    {
        $raw = $literal->getAttribute('rawValue');
        if (!is_string($raw)) {
            return null;
        }

        return $raw;
    }
}
