<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Body;
use Bidirekt\PhpSdk\Model\ConsumedResource;
use Bidirekt\PhpSdk\Model\Contract;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\NamedSchema;
use Bidirekt\PhpSdk\Model\ObjectNode;
use Bidirekt\PhpSdk\Model\Property;
use Bidirekt\PhpSdk\Model\ProvidedResource;
use Bidirekt\PhpSdk\Model\Response;
use Bidirekt\PhpSdk\Model\SchemaNames;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt;

/**
 * Reads the `@bidirekt provides` and `@bidirekt consumes` docblocks into resources, a docblock declaring
 * one handler with its `request` and `response` tags and the schemas its `schema` and `field` tags write.
 * Bodies and fields name schemas by their name in the contract.
 */
final class TagsToResources
{
    private const USAGES = [
        'provides' => ['@bidirekt provides <METHOD> <path>', 2],
        'consumes' => ['@bidirekt consumes <provider> <METHOD> <path>', 3],
    ];

    private const BLOCK_FIELD_KEYS = ['type', 'items', 'values', 'optional', 'nullable', 'description', 'union'];

    private const BODY_PATTERN = '/^([^\s[\]]+)(?:\[\](?: as ([^\s[\]]+))?)?$/';

    /** @var list<Diagnostic> */
    private array $errors = [];

    /** @var array<int, true> node ids of the elements a docblock already declared a handler on */
    private array $handled = [];

    /**
     * @param list<TaggedComment> $comments
     * @return ReadResult the resources and the lists their bodies declare
     */
    public static function commentsToResources(array $comments): ReadResult
    {
        $reader = new self();
        $contract = new Contract();
        foreach ($comments as $comment) {
            $read = $reader->commentToDeclarations($comment);
            if ($read === null) {
                continue;
            }
            array_push($contract->provides, ...$read->provides);
            array_push($contract->consumes, ...$read->consumes);
            array_push($contract->schemas, ...$read->schemas);
        }

        return new ReadResult($contract, $reader->errors, []);
    }

    private function commentToDeclarations(TaggedComment $comment): ?Contract
    {
        $handlers = $comment->tagsWith('provides', 'consumes');
        $bodies = $comment->tagsWith('request', 'response');
        if ($handlers === []) {
            foreach ($bodies as $tag) {
                $this->errors[] = new Diagnostic(
                    sprintf('"@bidirekt %s" needs a "@bidirekt provides" or "@bidirekt consumes" tag in the same comment', $tag->keyword),
                    $tag->location,
                );
            }

            return null;
        }
        $handler = array_shift($handlers);
        $id = spl_object_id($comment->element);
        if (isset($this->handled[$id])) {
            $this->errors[] = new Diagnostic(
                'a handler is declared more than once on this element: keep one "@bidirekt provides" or "@bidirekt consumes" comment',
                $handler->location,
            );

            return null;
        }
        $this->handled[$id] = true;
        foreach ($handlers as $extra) {
            $this->errors[] = new Diagnostic(sprintf('a comment declares one handler: move "%s" to its own comment', $extra->tagToText()), $extra->location);
        }
        $attributes = self::elementToHandlerAttributes($comment->element, $comment->file);
        if ($attributes !== []) {
            foreach ($attributes as $attribute) {
                $message = sprintf('#[%s] and "%s" are both on this handler: keep one', $attribute->name, $handler->tagToText());
                $this->errors[] = new Diagnostic($message, $attribute->location);
                $this->errors[] = new Diagnostic($message, $handler->location);
            }

            return null;
        }

        $read = $this->tagsToDeclarations($handler, $bodies) ?? new Contract();
        array_push($read->schemas, ...$this->tagsToBlockSchemas($comment->tags));

        return $read;
    }

    /**
     * The schemas a handler's docblock declares: `@bidirekt schema <name>` opens one, and each
     * `@bidirekt field <name> type=…` below it, up to the next `schema`, is one of its fields.
     *
     * @param list<CommentTag> $tags
     * @return list<NamedSchema>
     */
    private function tagsToBlockSchemas(array $tags): array
    {
        $schemas = [];
        $open = null;
        foreach ($tags as $tag) {
            if ($tag->keyword === 'schema') {
                $open = new BlockSchema($tag->location);
                $name = self::tagToBlockSchemaName($tag);
                if ($name instanceof Diagnostic) {
                    $this->errors[] = $name;

                    continue;
                }
                $open->name = $name;
                $schemas[] = $open;

                continue;
            }
            if ($tag->keyword !== 'field') {
                continue;
            }
            if ($open === null) {
                $this->errors[] = new Diagnostic('"@bidirekt field" in a handler\'s comment needs a "@bidirekt schema <name>" above it', $tag->location);

                continue;
            }
            $property = self::tagToBlockProperty($tag);
            if ($property instanceof Diagnostic) {
                $this->errors[] = $property;
                $open->hasUnreadField = true;

                continue;
            }
            $open->properties[] = $property;
        }
        $named = [];
        foreach ($schemas as $schema) {
            if ($schema->properties === [] && !$schema->hasUnreadField) {
                $this->errors[] = new Diagnostic(
                    sprintf('schema "%s" has no field: annotate at least one field with "@bidirekt field"', $schema->name),
                    $schema->location,
                );
            }
            $named[] = new NamedSchema($schema->name, new ObjectNode($schema->properties), $schema->location);
        }

        return $named;
    }

    private static function tagToBlockSchemaName(CommentTag $tag): string|Diagnostic
    {
        if ($tag->error !== null) {
            return $tag->error;
        }
        if (count($tag->arguments) !== 1 || str_contains($tag->arguments[0], '=')) {
            return new Diagnostic(sprintf('cannot read "%s" in a handler\'s comment: write "@bidirekt schema <name>"', $tag->tagToText()), $tag->location);
        }

        return $tag->arguments[0];
    }

    private static function tagToBlockProperty(CommentTag $tag): Property|Diagnostic
    {
        if ($tag->error !== null) {
            return $tag->error;
        }
        $arguments = $tag->arguments;
        $name = array_shift($arguments);
        if ($name === null || str_contains($name, '=')) {
            return new Diagnostic(
                sprintf('cannot read "%s" in a handler\'s comment: write "@bidirekt field <name> type=<type>"', $tag->tagToText()),
                $tag->location,
            );
        }
        $options = TagArgumentsToOptions::argumentsToOptions($tag, $arguments, self::BLOCK_FIELD_KEYS);
        if ($options instanceof Diagnostic) {
            return $options;
        }
        $node = $options->declared;
        if ($node === null) {
            return new Diagnostic(sprintf('field "%s" in a handler\'s comment needs its type: declare it with "type=<type>"', $name), $tag->location);
        }
        return $options->nodeToProperty($name, $node, $tag->location);
    }

    /**
     * The handler attributes on the element, or on the closures and arrow functions of the statement a
     * docblock is above, as the one passed to `$app->get(...)`: both declare the handler the tags declare.
     *
     * @return list<SdkAttribute>
     */
    private static function elementToHandlerAttributes(Node $element, ParsedFile $file): array
    {
        $found = [];
        foreach (self::elementToFunctions($element) as $function) {
            foreach (SdkAttributes::elementToSdkAttributes($function, $file) as $attribute) {
                if ($attribute->name === 'BidirektProvider' || $attribute->name === 'BidirektConsumer') {
                    $found[] = $attribute;
                }
            }
        }

        return $found;
    }

    /**
     * @return list<Node> the element when it is a function, else the outermost functions written in its statement
     */
    private static function elementToFunctions(Node $element): array
    {
        if ($element instanceof FunctionLike) {
            return [$element];
        }
        if (!$element instanceof Stmt\Expression && !$element instanceof Stmt\Return_) {
            return [];
        }

        return self::expressionToFunctions($element);
    }

    /**
     * @return list<Node>
     */
    private static function expressionToFunctions(Node $node): array
    {
        $functions = [];
        foreach ($node->getSubNodeNames() as $name) {
            $children = $node->{$name};
            if (!is_array($children)) {
                $children = [$children];
            }
            foreach ($children as $child) {
                if ($child instanceof FunctionLike) {
                    $functions[] = $child;

                    continue;
                }
                if ($child instanceof Node && !$child instanceof Stmt\ClassLike) {
                    array_push($functions, ...self::expressionToFunctions($child));
                }
            }
        }

        return $functions;
    }

    /**
     * @param list<CommentTag> $bodies
     */
    private function tagsToDeclarations(CommentTag $handler, array $bodies): ?Contract
    {
        $errors = [];
        $lists = [];
        $endpoint = self::handlerToEndpoint($handler);
        if ($endpoint instanceof Diagnostic) {
            $errors[] = $endpoint;
        }
        $request = null;
        $responses = [];
        foreach ($bodies as $tag) {
            if ($tag->error !== null) {
                $errors[] = $tag->error;

                continue;
            }
            if ($tag->keyword === 'request') {
                if ($request !== null) {
                    $errors[] = new Diagnostic('"@bidirekt request" is declared more than once in this comment: keep one', $tag->location);

                    continue;
                }
                $body = self::argumentsToBody($tag->arguments, $tag->location, $lists);
                if ($body instanceof Diagnostic) {
                    $errors[] = $body;

                    continue;
                }
                $request = $body;

                continue;
            }
            $response = self::tagToResponse($tag, $responses, $lists);
            if ($response instanceof Diagnostic) {
                $errors[] = $response;

                continue;
            }
            $responses[] = $response;
        }
        array_push($this->errors, ...$errors);
        if ($endpoint instanceof Diagnostic || $errors !== []) {
            return null;
        }
        [$provider, $method, $path] = $endpoint;
        if ($provider === null) {
            return new Contract([new ProvidedResource($method, $path, $request, $responses, $handler->location)], [], $lists);
        }

        return new Contract([], [new ConsumedResource($provider, $method, $path, $request, $responses, $handler->location)], $lists);
    }

    /**
     * @return array{string|null, string, string}|Diagnostic the provider (null on `provides`), the method and the path
     */
    private static function handlerToEndpoint(CommentTag $handler): array|Diagnostic
    {
        if ($handler->error !== null) {
            return $handler->error;
        }
        [$usage, $count] = self::USAGES[$handler->keyword] ?? self::USAGES['provides'];
        if (count($handler->arguments) !== $count) {
            return new Diagnostic(sprintf('cannot read "%s": write "%s"', $handler->tagToText(), $usage), $handler->location);
        }
        [$method, $path] = array_slice($handler->arguments, -2);
        $uppercase = HandlersToResources::methodToUppercaseError($method, $handler->location);
        if ($uppercase !== null) {
            return $uppercase;
        }
        $provider = null;
        if ($handler->keyword === 'consumes') {
            $provider = $handler->arguments[0];
        }

        return [$provider, $method, $path];
    }

    /**
     * @param list<Response> $declared
     * @param list<NamedSchema> $lists
     */
    private static function tagToResponse(CommentTag $tag, array $declared, array &$lists): Response|Diagnostic
    {
        $arguments = $tag->arguments;
        $status = array_shift($arguments);
        if ($status === null) {
            return new Diagnostic(sprintf('cannot read "%s": write "@bidirekt response <status> [schema]"', $tag->tagToText()), $tag->location);
        }
        $number = HandlersToResources::statusToNumberError($status, $tag->location);
        if ($number !== null) {
            return $number;
        }
        foreach ($declared as $response) {
            if ($response->status === (int) $status) {
                return new Diagnostic(sprintf('response status "%s" is declared more than once in this comment: keep one', $status), $tag->location);
            }
        }
        $body = self::argumentsToBody($arguments, $tag->location, $lists);
        if ($body instanceof Diagnostic) {
            return $body;
        }

        return new Response((int) $status, $body);
    }

    /**
     * @param list<string> $arguments
     * @param list<NamedSchema> $lists
     */
    private static function argumentsToBody(array $arguments, SourceLocation $location, array &$lists): Body|Diagnostic
    {
        if ($arguments === []) {
            return new Body(null, $location);
        }
        $text = implode(' ', $arguments);
        if (preg_match(self::BODY_PATTERN, $text, $match) !== 1) {
            return new Diagnostic(
                sprintf('cannot read "%s" as a body: write "<schema>", "<schema>[]" or "<schema>[] as <name>"', $text),
                $location,
            );
        }
        if (!str_contains($text, '[]')) {
            return new Body($match[1], $location);
        }
        $list = SchemaNames::listToNamedSchema($match[1], $match[2] ?? null, $location);
        $lists[] = $list;

        return new Body($list->name, $location);
    }
}
