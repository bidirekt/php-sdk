<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;
use Bidirekt\PhpSdk\Model\Body;
use Bidirekt\PhpSdk\Model\ConsumedResource;
use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\ProvidedResource;
use Bidirekt\PhpSdk\Model\Response;
use Bidirekt\PhpSdk\Model\SchemaNames;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node\FunctionLike;
use PhpParser\NodeFinder;

/**
 * Reads `#[BidirektProvider]` and `#[BidirektConsumer]` on methods, functions, closures and arrow
 * functions into resources, and their `#[BidirektSchema(name:, fields:)]` into schemas, each where it is
 * written: a handler in a trait or a parent counts once.
 */
final class HandlersToResources
{
    private FieldsToProperties $declaredFields;

    public function __construct(
        private ConstantExpressionToValue $evaluator,
        private TypeToSchemaNode $types,
    ) {
        $this->declaredFields = new FieldsToProperties(new DeclaredTypeToSchemaNode($types));
    }

    /**
     * @param list<string> $paths the project files `$files` reads whole
     * @return ReadResult the resources, the lists their bodies declare and the schemas declared on handlers
     */
    public function filesToResources(array $paths, ParsedFiles $files): ReadResult
    {
        $contract = new Contract();
        $errors = [];
        foreach ($paths as $path) {
            $file = $files->projectFile($path);
            foreach (self::fileToHandlers($file) as $handler) {
                foreach (SdkAttributes::elementToSdkAttributes($handler, $file) as $attribute) {
                    if ($attribute->name === 'BidirektSchema') {
                        [$schema, $schemaErrors] = $this->attributeToSchema($attribute, $file);
                        array_push($errors, ...$schemaErrors);
                        if ($schema !== null) {
                            $contract->schemas[] = $schema;
                        }

                        continue;
                    }
                    if ($attribute->name !== 'BidirektProvider' && $attribute->name !== 'BidirektConsumer') {
                        continue;
                    }
                    $read = $this->attributeToDeclarations($attribute, $file);
                    if (is_array($read)) {
                        array_push($errors, ...$read);

                        continue;
                    }
                    array_push($contract->provides, ...$read->provides);
                    array_push($contract->consumes, ...$read->consumes);
                    array_push($contract->schemas, ...$read->schemas);
                }
            }
        }

        return new ReadResult($contract, $errors, []);
    }

    /**
     * The methods, functions, closures and arrow functions of a file, in the order they are written.
     *
     * @return list<FunctionLike>
     */
    public static function fileToHandlers(ParsedFile $file): array
    {
        return array_values((new NodeFinder())->findInstanceOf($file->statements, FunctionLike::class));
    }

    public static function methodToUppercaseError(string $method, SourceLocation $location): ?Diagnostic
    {
        if ($method === strtoupper($method)) {
            return null;
        }

        return new Diagnostic(sprintf('method "%s" must be uppercase: write "%s"', $method, strtoupper($method)), $location);
    }

    public static function statusToNumberError(string $status, SourceLocation $location): ?Diagnostic
    {
        if (preg_match('/^[0-9]+$/', $status) !== 1) {
            return new Diagnostic(sprintf('response status "%s" must be a number', $status), $location);
        }
        if ((string) (int) $status !== $status) {
            return new Diagnostic(sprintf('response status "%s" must be written as %d', $status, (int) $status), $location);
        }

        return null;
    }

    /**
     * A schema declared on a handler, or on any method or function: named by `name`, its fields from `fields:`.
     * Once named, it is declared even with errors in its fields, so the references to it still resolve.
     *
     * @return array{NamedSchema|null, list<Diagnostic>}
     */
    private function attributeToSchema(SdkAttribute $attribute, ParsedFile $file): array
    {
        $arguments = $this->evaluator->argumentsToNamedAndErrors($attribute->attribute->args, SdkAttributes::classToParameters(BidirektSchema::class), '#[BidirektSchema]', $attribute->location, $file);
        $errors = $arguments->errors;
        if ($errors !== [] && !isset($arguments->values['name'])) {
            return [null, $errors];
        }
        $name = $arguments->requiredNameArgument(
            'name',
            '#[BidirektSchema] on a method or function must declare "name": write #[BidirektSchema(name: \'<name>\', fields: [new BidirektField(\'<field>\', type: …)])]',
        );
        if ($name instanceof Diagnostic) {
            return [null, [...$errors, $name]];
        }
        $properties = [];
        if (isset($arguments->values['fields'])) {
            [$properties, $fieldErrors] = $this->declaredFields->fieldsToProperties($arguments->values['fields']);
            array_push($errors, ...$fieldErrors);
        }
        if ($properties === [] && $errors === []) {
            $errors[] = new Diagnostic(sprintf('schema "%s" has no field: declare at least one field in "fields"', $name), $attribute->location);
        }

        return [new NamedSchema($name, new ObjectNode($properties), $attribute->location), $errors];
    }

    /**
     * One resource and the lists its bodies declare, or every error of the attribute.
     *
     * @return Contract|list<Diagnostic>
     */
    private function attributeToDeclarations(SdkAttribute $attribute, ParsedFile $file): Contract|array
    {
        $what = sprintf('#[%s]', $attribute->name);
        $parameters = SdkAttributes::classToParameters(BidirektProvider::class);
        if ($attribute->name === 'BidirektConsumer') {
            $parameters = SdkAttributes::classToParameters(BidirektConsumer::class);
        }
        $arguments = $this->evaluator->argumentsToNamed($attribute->attribute->args, $parameters, $what, $attribute->location, $file);
        if (is_array($arguments)) {
            return $arguments;
        }
        $errors = [];
        $provider = null;
        if ($attribute->name === 'BidirektConsumer') {
            $provider = self::requiredString($arguments, 'provider', $what, $errors);
        }
        $method = self::requiredString($arguments, 'method', $what, $errors);
        if ($method !== null) {
            $uppercase = self::methodToUppercaseError($method, $arguments->values['method']->location);
            if ($uppercase !== null) {
                $errors[] = $uppercase;
            }
        }
        $path = self::requiredString($arguments, 'path', $what, $errors);
        $lists = [];
        $request = null;
        if (array_key_exists('request', $arguments->values)) {
            $request = $this->valueToBody($arguments->values['request'], $errors, $lists);
        }
        $responses = $this->argumentsToResponses($arguments, $what, $errors, $lists);
        if ($errors !== [] || $method === null || $path === null) {
            return $errors;
        }
        if ($provider === null) {
            return new Contract([new ProvidedResource($method, $path, $request, $responses, $attribute->location)], [], $lists);
        }

        return new Contract([], [new ConsumedResource($provider, $method, $path, $request, $responses, $attribute->location)], $lists);
    }

    /**
     * @param list<Diagnostic> $errors
     */
    private static function requiredString(AttributeArguments $arguments, string $name, string $what, array &$errors): ?string
    {
        $value = $arguments->stringArgument($name);
        if ($value instanceof Diagnostic) {
            $errors[] = $value;

            return null;
        }
        if ($value === null) {
            $errors[] = new Diagnostic(sprintf('%s must declare "%s"', $what, $name), $arguments->location);
        }

        return $value;
    }

    /**
     * @param list<Diagnostic> $errors
     * @param list<NamedSchema> $lists
     * @return list<Response>
     */
    private function argumentsToResponses(AttributeArguments $arguments, string $what, array &$errors, array &$lists): array
    {
        $argument = $arguments->values['responses'] ?? null;
        if ($argument === null) {
            $errors[] = new Diagnostic(sprintf('%s must declare "responses"', $what), $arguments->location);

            return [];
        }
        if (!$argument->value instanceof ConstantArray) {
            $errors[] = new Diagnostic('"responses" must be an array: write [200 => Pet::class]', $argument->location);

            return [];
        }
        $responses = [];
        $statuses = [];
        foreach ($argument->value->items as $item) {
            if ($item->key === null) {
                $errors[] = new Diagnostic(
                    sprintf('a response has no status: write it as <status> => %s', $item->value->valueToText()),
                    $item->value->location,
                );

                continue;
            }
            $status = self::keyToStatus($item->key);
            $number = self::statusToNumberError($status, $item->key->location);
            if ($number !== null) {
                $errors[] = $number;

                continue;
            }
            if (isset($statuses[(int) $status])) {
                $errors[] = new Diagnostic(sprintf('"%s" is declared more than once in "responses": keep one', $status), $item->key->location);

                continue;
            }
            $statuses[(int) $status] = true;
            $body = $this->valueToBody($item->value, $errors, $lists);
            if ($body !== null) {
                $responses[] = new Response((int) $status, $body);
            }
        }

        return $responses;
    }

    private static function keyToStatus(ConstantValue $key): string
    {
        if (is_int($key->value)) {
            return $key->intLiteral ?? (string) $key->value;
        }
        if (is_string($key->value)) {
            return $key->value;
        }

        return $key->valueToText();
    }

    /**
     * @param list<Diagnostic> $errors
     * @param list<NamedSchema> $lists
     */
    private function valueToBody(ConstantValue $value, array &$errors, array &$lists): ?Body
    {
        $body = $this->valueToHandlerBody($value);
        if (is_array($body)) {
            array_push($errors, ...$body);

            return null;
        }
        if ($body->list !== null) {
            $lists[] = $body->list;
        }

        return $body->body;
    }

    /**
     * @return HandlerBody|list<Diagnostic>
     */
    private function valueToHandlerBody(ConstantValue $value): HandlerBody|array
    {
        if ($value->value === null) {
            return new HandlerBody(new Body(null, $value->location), null);
        }
        if ($value->value instanceof ConstantArray) {
            return $this->listToHandlerBody($value, $value->value);
        }
        $schema = $this->types->valueToRefNode($value);
        if ($schema === null) {
            return [self::unreadableBody($value)];
        }
        if (is_array($schema)) {
            return $schema;
        }

        return new HandlerBody(new Body($schema->schema, $value->location), null);
    }

    /**
     * @return HandlerBody|list<Diagnostic>
     */
    private function listToHandlerBody(ConstantValue $value, ConstantArray $array): HandlerBody|array
    {
        $options = self::arrayToListOptions($array);
        if ($options === null) {
            return [self::unreadableBody($value)];
        }
        $items = $this->types->valueToRefNode($options['list']);
        if ($items === null) {
            return [new Diagnostic(
                sprintf('cannot read %s as the items of a list: write a class with #[BidirektSchema], such as Pet::class, or a schema name', $options['list']->valueToText()),
                $options['list']->location,
            )];
        }
        if (is_array($items)) {
            return $items;
        }
        $name = (new AttributeArguments($options, $value->location))->nameArgument('name');
        if ($name instanceof Diagnostic) {
            return [$name];
        }
        $list = SchemaNames::listToNamedSchema($items->schema, $name, $value->location);

        return new HandlerBody(new Body($list->name, $value->location), $list);
    }

    /**
     * The entries of `[Pet::class]` or `['list' => Pet::class, 'name' => '…']`, or null for any other array.
     *
     * @return array{list: ConstantValue, name?: ConstantValue}|null
     */
    private static function arrayToListOptions(ConstantArray $array): ?array
    {
        if (count($array->items) === 1 && $array->items[0]->key === null) {
            return ['list' => $array->items[0]->value];
        }
        $options = [];
        foreach ($array->items as $item) {
            $key = $item->key?->value;
            if (($key !== 'list' && $key !== 'name') || isset($options[$key])) {
                return null;
            }
            $options[$key] = $item->value;
        }
        if (!isset($options['list'])) {
            return null;
        }

        return $options;
    }

    private static function unreadableBody(ConstantValue $value): Diagnostic
    {
        return new Diagnostic(
            sprintf('cannot read %s as a body: write a class with #[BidirektSchema], a schema name, null, [Pet::class] or [\'list\' => Pet::class, \'name\' => \'<name>\']', $value->valueToText()),
            $value->location,
        );
    }
}
