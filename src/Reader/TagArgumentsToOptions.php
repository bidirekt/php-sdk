<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\ArrayNode;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\MapNode;
use Bidirekt\PhpSdk\Model\PrimitiveNode;
use Bidirekt\PhpSdk\Model\RefNode;
use Bidirekt\PhpSdk\Model\SchemaNode;
use Bidirekt\PhpSdk\Model\UnionMember;
use Bidirekt\PhpSdk\Model\UnionNode;

/**
 * Reads the `key=value` arguments of a tag. A type is written with the contract's keys and never nests:
 * `type=Pet`, `type=array items=Pet`, `type=map values=string`, `union={card: Card, pix: Pix}`.
 */
final class TagArgumentsToOptions
{
    public const FIELD_KEYS = ['type', 'items', 'values', 'name', 'optional', 'nullable', 'description', 'union'];

    private const USAGES = [
        'type' => '"type=" followed by string, integer, float, number, boolean, a schema name, array or map',
        'items' => '"items=" followed by string, integer, float, number, boolean or a schema name',
        'values' => '"values=" followed by string, integer, float, number, boolean or a schema name',
        'name' => '"name=<name>"',
        'optional' => '"optional=true" or "optional=false"',
        'nullable' => '"nullable=true" or "nullable=false"',
        'description' => 'description="<text>"',
        'union' => '"union={<key>: <schema>, ...}"',
    ];

    private const SCHEMA_NAME_PATTERN = '/^[^\s<>[\],:={}]+$/';

    private const UNION_MEMBER_PATTERN = '/^([^\s:,]+)\s*:\s*([^\s:,]+)$/';

    /**
     * @param list<string> $arguments
     * @param list<string> $keys the keys the tag takes
     */
    public static function argumentsToOptions(CommentTag $tag, array $arguments, array $keys): FieldOptions|Diagnostic
    {
        if ($tag->error !== null) {
            return $tag->error;
        }
        $options = new FieldOptions();
        $values = [];
        foreach ($arguments as $argument) {
            $separator = strpos($argument, '=');
            $key = '';
            if ($separator !== false) {
                $key = substr($argument, 0, $separator);
            }
            if ($key === '' || !in_array($key, $keys, true)) {
                return self::unreadable($argument, $tag, self::keysToUsage($keys));
            }
            if (isset($values[$key])) {
                return new Diagnostic(sprintf('"%s=" is declared more than once in "@bidirekt %s": keep one', $key, $tag->keyword), $tag->location);
            }
            $value = substr($argument, (int) $separator + 1);
            if ($key === 'description' && preg_match('/^"[^"]*"$/', $value) === 1) {
                $value = substr($value, 1, -1);
            }
            $values[$key] = $value;
            $error = self::optionToError($key, $value, $argument, $tag);
            if ($error !== null) {
                return $error;
            }
        }
        $options->name = $values['name'] ?? null;
        $options->description = $values['description'] ?? null;
        if (isset($values['optional'])) {
            $options->optional = $values['optional'] === 'true';
        }
        if (isset($values['nullable'])) {
            $options->nullable = $values['nullable'] === 'true';
        }
        $declared = self::valuesToDeclared($values, $tag);
        if ($declared instanceof Diagnostic) {
            return $declared;
        }
        $options->declared = $declared;

        return $options;
    }

    /**
     * Why the value is not one the key takes, or null when it is.
     */
    private static function optionToError(string $key, string $value, string $argument, CommentTag $tag): ?Diagnostic
    {
        $usage = self::USAGES[$key] ?? '';
        if ($key === 'type' || $key === 'items' || $key === 'values') {
            if (self::isType($value, $key)) {
                return null;
            }
            $fix = DeclaredTypeRules::shortFormToFix($value, AnnotationForm::tag());
            if ($key === 'type' && $fix !== null) {
                $usage = $fix;
            }

            return self::unreadable($argument, $tag, $usage);
        }
        if ($key === 'union' && self::valueToUnion($value) === null) {
            return self::unreadable($argument, $tag, $usage);
        }
        if (($key === 'optional' || $key === 'nullable') && $value !== 'true' && $value !== 'false') {
            return self::unreadable($argument, $tag, $usage);
        }
        if ($key === 'name' && $value === '') {
            return self::unreadable($argument, $tag, $usage);
        }
        if ($key === 'description') {
            $error = DescriptionToError::descriptionToError($value);
            if ($error !== null) {
                return new Diagnostic($error, $tag->location);
            }
        }

        return null;
    }

    /**
     * The whole type of `type=`, `items=`, `values=` and `union=`, once each is known to be readable.
     *
     * @param array<string, string> $values key => value as written
     */
    private static function valuesToDeclared(array $values, CommentTag $tag): SchemaNode|Diagnostic|null
    {
        $type = $values['type'] ?? null;
        $error = DeclaredTypeRules::partsToError(
            $type !== null,
            $type,
            isset($values['items']),
            isset($values['values']),
            isset($values['union']),
            AnnotationForm::tag(),
            sprintf('"@bidirekt %s"', $tag->keyword),
        );
        if ($error !== null) {
            return new Diagnostic($error[0], $tag->location);
        }
        if (isset($values['union'])) {
            return self::valueToUnion($values['union']);
        }
        if ($type === 'array' && isset($values['items'])) {
            return new ArrayNode(self::leafToNode($values['items']));
        }
        if ($type === 'map' && isset($values['values'])) {
            return new MapNode(self::leafToNode($values['values']));
        }
        if ($type === null) {
            return null;
        }

        return self::leafToNode($type);
    }

    /**
     * Whether the value is a type the key takes: a primitive or a schema name, and `array` or `map` in `type=`.
     */
    private static function isType(string $value, string $key): bool
    {
        if ($value === 'array' || $value === 'map') {
            return $key === 'type';
        }

        return preg_match(self::SCHEMA_NAME_PATTERN, $value) === 1;
    }

    private static function leafToNode(string $value): SchemaNode
    {
        if (in_array($value, PrimitiveNode::TYPES, true)) {
            return new PrimitiveNode($value);
        }

        return new RefNode($value);
    }

    private static function valueToUnion(string $value): ?UnionNode
    {
        if (preg_match('/^\{([^{}]*)\}$/', $value, $braces) !== 1) {
            return null;
        }
        $members = [];
        $keys = [];
        foreach (explode(',', $braces[1]) as $member) {
            if (preg_match(self::UNION_MEMBER_PATTERN, trim($member), $match) !== 1 || preg_match(self::SCHEMA_NAME_PATTERN, $match[2]) !== 1 || isset($keys[$match[1]])) {
                return null;
            }
            $keys[$match[1]] = true;
            $members[] = new UnionMember($match[1], new RefNode($match[2]));
        }

        return new UnionNode($members);
    }

    /**
     * @param list<string> $keys
     */
    private static function keysToUsage(array $keys): string
    {
        if (count($keys) === 1) {
            return self::USAGES[$keys[0]] ?? '';
        }

        return sprintf('"<key>=<value>" with key %s', NamesToChoice::namesToChoice($keys));
    }

    private static function unreadable(string $argument, CommentTag $tag, string $usage): Diagnostic
    {
        return new Diagnostic(sprintf('cannot read "%s" in "@bidirekt %s": write %s', $argument, $tag->keyword, $usage), $tag->location);
    }
}
