<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\Property;

/**
 * Reads the `fields:` of a `#[BidirektSchema]`, each a `new BidirektField('<name>', type: …)`, into properties.
 */
final class FieldsToProperties
{
    public function __construct(
        private DeclaredTypeToSchemaNode $declared,
    ) {}

    /**
     * @return array{list<Property>, list<Diagnostic>} the fields that could be read, and the errors of the others
     */
    public function fieldsToProperties(ConstantValue $fields): array
    {
        if (!$fields->value instanceof ConstantArray) {
            return [[], [self::notAField($fields)]];
        }
        $properties = [];
        $errors = [];
        foreach ($fields->value->items as $item) {
            if ($item->key !== null || !$item->value->value instanceof FieldConstruction) {
                $errors[] = self::notAField($item->key ?? $item->value);

                continue;
            }
            $property = $this->fieldToProperty($item->value->value->arguments, $item->value);
            if (is_array($property)) {
                array_push($errors, ...$property);

                continue;
            }
            $properties[] = $property;
        }

        return [$properties, $errors];
    }

    /**
     * @return Property|list<Diagnostic>
     */
    private function fieldToProperty(AttributeArguments $arguments, ConstantValue $field): Property|array
    {
        $name = $arguments->stringArgument('name');
        if ($name instanceof Diagnostic) {
            return [$name];
        }
        if ($name === null) {
            return [new Diagnostic('new BidirektField(…) in "fields" must declare "name": write the field\'s name as its first argument', $field->location)];
        }
        if ($name === '') {
            return [new Diagnostic('"name" is empty: write the field\'s name', $arguments->values['name']->location)];
        }
        $options = ArgumentsToFieldOptions::argumentsToFieldOptions($arguments);
        if (is_array($options)) {
            return $options;
        }
        $node = $this->declared->argumentsToSchemaNode($arguments, $field->location, 'new BidirektField(…)');
        if (is_array($node)) {
            return $node;
        }
        if ($node === null) {
            return [new Diagnostic(sprintf('field "%s" in "fields" needs its type: declare "type" or "union"', $name), $field->location)];
        }
        $node->nullable = $options->nullable ?? $node->nullable;
        $node->optional = $options->optional ?? false;
        $node->description = $options->description;

        return new Property($name, $node, $field->location);
    }

    private static function notAField(ConstantValue $value): Diagnostic
    {
        return new Diagnostic(
            sprintf('cannot read %s as a field of "fields": write new BidirektField(\'<name>\', type: …), as in fields: [new BidirektField(\'petId\', type: \'integer\')]', $value->valueToText()),
            $value->location,
        );
    }
}
