<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

/**
 * The rules of a declared type that `#[BidirektField]` and `@bidirekt field` share, the messages written
 * in the syntax of each.
 */
final class DeclaredTypeRules
{
    private const NESTED_KEYS = ['array' => 'items', 'map' => 'values'];

    /**
     * The first rule the declared parts break, or null when they keep every rule.
     *
     * @param string|null $type the `type` when it is a name, as `array` or `map`
     * @param string $where how messages name the place the parts are written
     * @return array{string, 'type'|'items'|'values'|null}|null the message and the part it is about, null for the whole declaration
     */
    public static function partsToError(bool $hasType, ?string $type, bool $hasItems, bool $hasValues, bool $hasUnion, AnnotationForm $form, string $where): ?array
    {
        if ($hasType && $hasUnion) {
            return [sprintf($form->typeAndUnion, $where), null];
        }
        if ($hasItems && $type !== 'array') {
            return [sprintf($form->nestedWithoutKind, 'array', 'items', $where), 'items'];
        }
        if ($hasValues && $type !== 'map') {
            return [sprintf($form->nestedWithoutKind, 'map', 'values', $where), 'values'];
        }
        if ($type === 'array' && !$hasItems) {
            return [sprintf($form->kindWithoutNested, 'array', 'items', $where), 'type'];
        }
        if ($type === 'map' && !$hasValues) {
            return [sprintf($form->kindWithoutNested, 'map', 'values', $where), 'type'];
        }

        return null;
    }

    /**
     * How to write a short form such as `Pet[]` or `map<string, Pet>` with the declared parts, or null for
     * any other type.
     */
    public static function shortFormToFix(string $type, AnnotationForm $form): ?string
    {
        if (str_ends_with($type, '[]')) {
            return self::partsToFix('array', substr($type, 0, -2), $form);
        }
        if (preg_match('/^map<(?:.*,)?\s*(.*)>$/', $type, $match) === 1) {
            return self::partsToFix('map', $match[1], $form);
        }

        return null;
    }

    /**
     * @param 'array'|'map' $type
     */
    private static function partsToFix(string $type, string $nested, AnnotationForm $form): string
    {
        return sprintf($form->shortForm, $type, self::NESTED_KEYS[$type], $nested);
    }
}
