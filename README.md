# Bidirekt PHP SDK

[![CI](https://img.shields.io/github/actions/workflow/status/bidirekt/php-sdk/ci.yml?branch=main&label=CI)](https://github.com/bidirekt/php-sdk/actions/workflows/ci.yml) [![License](https://img.shields.io/github/license/bidirekt/php-sdk)](LICENSE)

In Bidirekt, each side of an integration declares in a contract file what it provides and what it consumes. This SDK writes that file from the code of a service on PHP 8.0 or later: you annotate the schemas and the handlers that provide or consume each resource, with attributes or with `@bidirekt` tags in docblocks, run `generate`, and get `contracts/generated.yaml`, ready for `bidirekt validate` and `bidirekt publish`. It is the service's only contract file: once a service adopts the SDK, every resource and schema it provides or consumes is annotated in its code.

## Installation

The SDK needs PHP 8.0 or later and Composer 2.2 or later. Install it as a dev dependency:

```sh
composer require --dev bidirekt/php-sdk
```

To read PHP 8.4 and 8.5 code, the SDK needs `nikic/php-parser` 5.6.2 or later. If Composer says your lock file fixes an older version (a Laravel project locks it through `psy/psysh`), run `composer require --dev bidirekt/php-sdk -W`.

The package holds the four attributes and the `generate` command. An attribute runs nothing: PHP loads its class only when code calls `newInstance()` on it, and `generate` reads the source instead of running it. So a production install with `composer install --no-dev` still runs your app. `generate` reads code of any PHP version; two forms need a newer PHP to run your app:

- `new BidirektField(...)` inside `fields:` needs PHP 8.1, as does an enum. On PHP 8.0, declare a [schema without a class](#schemas-without-a-class) with tags.
- `Enum::Case->value` as an attribute argument needs PHP 8.2.

## Quickstart

`petstore_php` is a Slim service. It provides `GET /pets/*` and `POST /pets`, and consumes `GET /ratings/*` from `petstore_reviews`. `#[BidirektSchema]` marks each schema and `#[BidirektField]` each field of the contract, on a property or a promoted constructor parameter; a field without it stays out of the contract. `Pet` uses each kind of field:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /**
     * @param list<string> $tags
     * @param array<string, string> $notes
     */
    public function __construct(
        #[BidirektField] public int $id,
        #[BidirektField] public string $name,
        #[BidirektField] public PetStatus $status,
        #[BidirektField] public Owner $owner,
        #[BidirektField] public array $tags,
        #[BidirektField] public array $notes,
        #[BidirektField(type: 'float', description: 'In kilograms')] public float $weight,
        #[BidirektField] public ?string $birthDate,
        #[BidirektField(union: ['person' => Owner::class, 'shelter' => Shelter::class])]
        public Owner|Shelter $adoptedFrom,
    ) {}

    #[BidirektField(optional: true)]
    public string $nickname;
}
```

`src/PetStatus.php`
```php
<?php

namespace App;

enum PetStatus: string
{
    case Available = 'available';
    case Adopted = 'adopted';
}
```

`src/Owner.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    public function __construct(#[BidirektField] public string $name) {}
}
```

`src/Shelter.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Shelter
{
    public function __construct(#[BidirektField] public string $city) {}
}
```

`src/NewPet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class NewPet
{
    public function __construct(#[BidirektField] public string $name) {}
}
```

`src/Rating.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Rating
{
    public function __construct(
        #[BidirektField] public float $average,
        #[BidirektField] public int $count,
    ) {}
}
```

`#[BidirektProvider]` on a handler declares what it provides. Slim routes `/pets/{id}` to `show`, and the path is declared `/pets/*`: a `*` for each dynamic segment, never the framework's placeholder.

`src/PetController.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PetController
{
    public function __construct(private PetRepository $pets) {}

    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    public function show(Request $request, Response $response, array $args): Response
    {
        $pet = $this->pets->find((int) $args['id']);

        return $pet === null ? $response->withStatus(404) : self::json($response, $pet);
    }

    #[BidirektProvider(method: 'POST', path: '/pets', request: NewPet::class, responses: [201 => Pet::class, 400 => null])]
    public function create(Request $request, Response $response): Response
    {
        $name = $request->getParsedBody()['name'] ?? null;

        return is_string($name) ? self::json($response->withStatus(201), $this->pets->add($name)) : $response->withStatus(400);
    }

    private static function json(Response $response, Pet $pet): Response
    {
        $response->getBody()->write((string) json_encode($pet, JSON_PRESERVE_ZERO_FRACTION));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

`#[BidirektConsumer]` declares what a client consumes, and from which provider:

`src/ReviewsClient.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektConsumer;
use GuzzleHttp\Client;

final class ReviewsClient
{
    public function __construct(private Client $http) {}

    #[BidirektConsumer(provider: 'petstore_reviews', method: 'GET', path: '/ratings/*', responses: [200 => Rating::class, 404 => null])]
    public function rating(int $petId): ?Rating
    {
        $response = $this->http->get("/ratings/{$petId}", ['http_errors' => false]);
        $body = json_decode((string) $response->getBody(), true);

        return $response->getStatusCode() === 404 ? null : new Rating($body['average'], $body['count']);
    }
}
```

Tags say the same in `/** */` docblocks, for code that can't use attributes or prefers docblocks. A service can mix both forms, never on the same handler, schema or field, so here `Pet` and `PetController` switch to tags and the other classes keep their attributes. A tag names a schema by its name in the contract, and `ReviewsClient` would declare `@bidirekt consumes petstore_reviews GET /ratings/*` with the same `@bidirekt response` lines:

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Pet
{
    /**
     * @param list<string> $tags
     * @param array<string, string> $notes
     */
    public function __construct(
        /** @bidirekt field */
        public int $id,
        /** @bidirekt field */
        public string $name,
        /** @bidirekt field */
        public PetStatus $status,
        /** @bidirekt field */
        public Owner $owner,
        /** @bidirekt field */
        public array $tags,
        /** @bidirekt field */
        public array $notes,
        /** @bidirekt field type=float description="In kilograms" */
        public float $weight,
        /** @bidirekt field */
        public ?string $birthDate,
        /** @bidirekt field union={person: Owner, shelter: Shelter} */
        public Owner|Shelter $adoptedFrom,
    ) {}

    /** @bidirekt field optional=true */
    public string $nickname;
}
```

`src/PetController.php`
```php
<?php

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PetController
{
    public function __construct(private PetRepository $pets) {}

    /**
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200 Pet
     * @bidirekt response 404
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $pet = $this->pets->find((int) $args['id']);

        return $pet === null ? $response->withStatus(404) : self::json($response, $pet);
    }

    /**
     * @bidirekt provides POST /pets
     * @bidirekt request NewPet
     * @bidirekt response 201 Pet
     * @bidirekt response 400
     */
    public function create(Request $request, Response $response): Response
    {
        $name = $request->getParsedBody()['name'] ?? null;

        return is_string($name) ? self::json($response->withStatus(201), $this->pets->add($name)) : $response->withStatus(400);
    }

    private static function json(Response $response, Pet $pet): Response
    {
        $response->getBody()->write((string) json_encode($pet, JSON_PRESERVE_ZERO_FRACTION));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

Run `generate` in the project folder, the one with `composer.json`:

```sh
vendor/bin/php-sdk generate
```

Either form writes the same `contracts/generated.yaml`:

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets:
      post:
        request: NewPet
        responses:
          201: Pet
          400: ~
    /pets/*:
      get:
        responses:
          200: Pet
          404: ~
consumes:
  petstore_reviews:
    rest:
      /ratings/*:
        get:
          responses:
            200: Rating
            404: ~
schemas:
  NewPet:
    type: object
    properties:
      name:
        type: string
  Owner:
    type: object
    properties:
      name:
        type: string
  Pet:
    type: object
    properties:
      id:
        type: integer
      name:
        type: string
      status:
        ref: PetStatus
      owner:
        ref: Owner
      tags:
        type: array
        items:
          type: string
      notes:
        type: map
        values:
          type: string
      weight:
        type: float
        description: In kilograms
      birthDate:
        type: string
        nullable: true
      adoptedFrom:
        type: union
        members:
          person:
            ref: Owner
          shelter:
            ref: Shelter
      nickname:
        type: string
        optional: true
  PetStatus:
    type: enum
    members: ["available", "adopted"]
  Rating:
    type: object
    properties:
      average:
        type: number
      count:
        type: integer
  Shelter:
    type: object
    properties:
      city:
        type: string
```

Field by field:

- `status`: an enum with string values is a `ref` to an `enum` schema named after it.
- `owner`: a schema class is a `ref`, the nested object.
- `tags` and `notes`: an `array` takes the type of its items or values from the PHPDoc, here the constructor's `@param`: `list<string>` is a list, `array<string, string>` a map.
- `weight`: declares a `description`, and `type: 'float'` because the controller encodes with `JSON_PRESERVE_ZERO_FRACTION`; without it a PHP `float` is a `number`, like `Rating::$average` ([What `json_encode` writes](#what-json_encode-writes)).
- `birthDate`: `?string` makes it `nullable`.
- `adoptedFrom`: a union of schema classes declares the key of each member in `union`.
- `nickname`: declares `optional`, because the code leaves it unset when the pet has none, and `json_encode` leaves an unset typed property out.

## Schemas without a class

A body with no class to annotate, like an array a handler builds or a Laravel API Resource, declares its schema on the handler: `#[BidirektSchema(name: ..., fields: [...])]`, where `name` is required and each field is a `new BidirektField('<name>', type: ...)` with a `type` or a `union`. With tags, `@bidirekt schema <Name>` in the handler's docblock opens a schema and each following `@bidirekt field <name> type=...` is a field of it, until the next `schema` or the end of the docblock. `new` in an attribute needs PHP 8.1; on PHP 8.0, use the tags. These schemas belong to the whole contract, so other handlers and fields reference them by name:

`src/StatsController.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class StatsController
{
    #[BidirektProvider(method: 'GET', path: '/stats', responses: [200 => 'PetStats'])]
    #[BidirektSchema(name: 'PetStats', fields: [
        new BidirektField('total', type: 'integer'),
        new BidirektField('byStatus', type: 'map', values: 'integer'),
    ])]
    public function show(Request $request, Response $response): Response
    {
        $response->getBody()->write((string) json_encode(['total' => 3, 'byStatus' => ['available' => 2, 'adopted' => 1]]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

`src/StatsController.php`
```php
<?php

namespace App;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class StatsController
{
    /**
     * @bidirekt provides GET /stats
     * @bidirekt response 200 PetStats
     *
     * @bidirekt schema PetStats
     * @bidirekt field total type=integer
     * @bidirekt field byStatus type=map values=integer
     */
    public function show(Request $request, Response $response): Response
    {
        $response->getBody()->write((string) json_encode(['total' => 3, 'byStatus' => ['available' => 2, 'adopted' => 1]]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /stats:
      get:
        responses:
          200: PetStats
schemas:
  PetStats:
    type: object
    properties:
      total:
        type: integer
      byStatus:
        type: map
        values:
          type: integer
```

On a class, `fields:` declares the fields of a body the class builds by hand, as in `jsonSerialize()` or a Laravel API Resource's `toArray()`. Without `name`, the schema is named after the class, and these fields come after the ones annotated in the class or its parents. A Laravel API Resource's parent, `JsonResource`, is read from `vendor/`, so run `composer install` before `generate`.

## The `generate` command

```sh
vendor/bin/php-sdk generate
vendor/bin/php-sdk generate --check
vendor/bin/php-sdk generate --project apps/api --out apps/api/contracts/generated.yaml
```

- `--check` writes nothing and exits with 1 when the file on disk differs from what `generate` would write (`\r\n` counts as `\n`): `"contracts/generated.yaml" is out of date: run generate without --check`, or when it doesn't exist: `"contracts/generated.yaml" does not exist: run generate without --check`. Use it in CI.
- `--project <folder>` reads another project, and writes to its folder.
- `--out <file>` writes to another file.

`contracts/generated.yaml` is the service's only contract file. Commit it with the code, regenerate it whenever an annotation or an annotated type changes, and never edit it by hand: the next `generate` writes over it. From there the flow is the same as with a hand-written contract:

```sh
vendor/bin/php-sdk generate
bidirekt validate contracts/generated.yaml --participant petstore_php --environment production
bidirekt publish contracts/generated.yaml --participant petstore_php --version 1.4.0
bidirekt can-i-deploy petstore_php --version 1.4.0 --environment production
# deploy
bidirekt record-deployment petstore_php --version 1.4.0 --environment production
```

`generate` reads the source and never runs your app. With a `composer.json`, it reads every `.php` file of the folder except `vendor/` (or the `config.vendor-dir`), `node_modules/`, hidden folders and the paths of `autoload-dev`; without one, every `.php` file except the same folders. It reads `vendor/` only to find a parent, a trait, an enum or a constant your annotated code uses, through Composer's class maps (`vendor/composer/autoload_psr4.php`, `autoload_namespaces.php`, `autoload_classmap.php`) and `vendor/composer/installed.json`, from the `composer.json` closest to the project, going up as Composer does in a monorepo but never to your home folder or above. It loads those maps with `require`, since they only return arrays, and never loads `vendor/autoload.php` nor the `autoload.files`. A package installed from a Composer `path` repository is read as your code. A file that never mentions `bidirekt` is parsed only when your annotated code uses a class it declares; a syntax error stops `generate` in a file with annotations or in a file it parses for that reason.

The file starts with `# Generated by bidirekt/php-sdk. Do not edit.` and has the `provides`, `consumes` and `schemas` sections that have content: resources by path, method and status, schemas in alphabetical order, fields and members in the order of the code. Every schema is written, even one no handler references.

Each error goes to stderr as `file:line message`, with the file relative to the current folder, sorted by file and line; on an error nothing is written and the exit code is 1. A warning is printed as `file:line warning: message` in the same list and doesn't change the exit code. A project with no annotation writes nothing and exits with 1.

## Reference

### Attributes

`generate` recognizes an attribute by its full name, `Bidirekt\PhpSdk\BidirektField` and the others, after it resolves the file's `use`, alias and namespace; a `#[BidirektField]` without its `use` is an error. Written without names, the arguments follow the order below.

| Attribute            | Goes on                                                                                                       | Arguments                                                                    |
| -------------------- | ------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `#[BidirektSchema]`  | a class, an enum with string values, or a handler ([without a class](#schemas-without-a-class))               | `name`, `fields`                                                             |
| `#[BidirektField]`   | a property or a promoted constructor parameter, or `new BidirektField(...)` in `fields:`                      | `name`, `type`, `items`, `values`, `optional`, `nullable`, `description`, `union` |
| `#[BidirektProvider]` | a method, a function, a closure or an arrow function                                                         | `method`, `path`, `responses`, `request`                                     |
| `#[BidirektConsumer]` | a method, a function, a closure or an arrow function                                                         | `provider`, `method`, `path`, `responses`, `request`                         |

An argument is read from the code, never run: a literal, an array, `X::class`, a class constant (also from a class or interface in `vendor/`), or `Enum::Case->value`, which PHP accepts in an attribute from 8.2. A status is an integer or a class constant that holds one, like `StatusCodeInterface::STATUS_OK`. Anything else, like a concatenation or a global constant, stops `generate`, as does a misspelled argument name or a missing required one.

### Fields

`#[BidirektField(...)]` and `@bidirekt field key=value ...` take the same options:

| Attribute                                                        | Tag                                       | What it declares                                                                                                |
| ---------------------------------------------------------------- | ----------------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `name: 'birth_date'`                                             | `name=birth_date`                         | the field's name in the contract; without it, the property's name                                               |
| `type: 'float'`                                                  | `type=float`                              | the whole type of the field, see [Types](#types)                                                                |
| `items: 'Owner'`                                                 | `items=Owner`                             | the type of the items of `type: 'array'`                                                                        |
| `values: 'string'`                                               | `values=string`                           | the type of the values of `type: 'map'`                                                                         |
| `optional: true`                                                 | `optional=true`                           | whether the field can be absent; without it, it can't                                                           |
| `nullable: true`                                                 | `nullable=true`                           | whether the field can be `null`; without it, from `?T` or `null` in the type                                    |
| `description: 'In kilograms'`                                    | `description="In kilograms"`              | the field's description, ASCII letters and digits with single spaces between words; a plain docblock is not one |
| `union: ['person' => Owner::class, 'shelter' => Shelter::class]` | `union={person: Owner, shelter: Shelter}` | the key of each member of a union of schemas                                                                    |

A promoted constructor parameter is a field like a property. An annotation on a parameter that is not promoted, or on a `static` property, is not read, and `generate` warns.

### Handlers

A handler declares one resource: its method in uppercase (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) and its path as the contract writes it, with a `*` for each dynamic segment. `generate` never reads a framework's routes, and writes the path as you declare it; `bidirekt validate` rejects a `{id}`. The request and each response status take a body:

| Body                     | Attribute                                           | Tag                                   | Contract        |
| ------------------------ | --------------------------------------------------- | ------------------------------------- | --------------- |
| a schema class           | `Pet::class`                                        | `Pet`                                 | `Pet`           |
| a schema by its name     | `'PetStats'`                                        | `PetStats`                            | `PetStats`      |
| a list                   | `[Pet::class]`                                      | `Pet[]`                               | `PetList`       |
| a list with its own name | `['list' => Pet::class, 'name' => 'PetCollection']` | `Pet[] as PetCollection`              | `PetCollection` |
| no body                  | `null`                                              | nothing after the status or `request` | `~`             |

Without a request (no `request` argument, no `@bidirekt request`), the resource has no `request` key. A status written twice (`200` and `'200'`, which PHP keeps as one) or with a leading zero (`0201`) stops `generate`. A list is written once however many handlers use it. The same method and path provided by two handlers stops `generate`; a handler written in a trait or a parent class counts once. The same resource consumed by several handlers is written once with the statuses of all of them, which must declare the same request and the same schema for a status they share.

### Tags

A tag is `@bidirekt <keyword>` at the start of a line of a `/** */` docblock, with its arguments separated by spaces:

| Tag                                              | Goes                                                                         |
| ------------------------------------------------ | ---------------------------------------------------------------------------- |
| `@bidirekt schema` or `@bidirekt schema name=PetResponse` | above a class or an enum with string values                         |
| `@bidirekt field [key=value ...]`                | above a property, or a promoted constructor parameter                        |
| `@bidirekt schema <Name>` and `@bidirekt field <name> type=...` | in a handler's docblock ([without a class](#schemas-without-a-class)) |
| `@bidirekt provides <METHOD> <path>`             | above a method, a function, a closure, or a route call like `$app->get(...)` or `Route::get(...)` |
| `@bidirekt consumes <provider> <METHOD> <path>`  | the same                                                                     |
| `@bidirekt request [schema]`                     | after `provides` or `consumes`; with no schema, `request: ~`                 |
| `@bidirekt response <status> [schema]`           | after `provides` or `consumes`, one per status                               |

- Only a `description` value is quoted, and it holds ASCII letters and digits with single spaces between words, as in the attribute; only a `union=` value is grouped in `{...}` (spaces inside it are optional); quotes or braces anywhere else stop `generate`. `items=` and `values=` take a primitive or a schema name: a tag doesn't nest, so a list of lists needs the PHPDoc.
- In a path, write a `*` followed by another segment as `*\/` (`/pets/*\/photos`): a bare `*/` would end the docblock. `generate` reads `*\/` as `*/`.
- A docblock declares at most one handler. It goes above the element and above its attributes, which is where PHP attaches it; tags in two docblocks on the same element stop `generate`.
- `/* */`, `//` and `#` comments are not read, nor a `@bidirekt` later in a line, in a fenced block or in an `@example`. `generate` warns about each tag it leaves unread, a docblock it can't attach to an element, and a misspelled `@Bidirekt`. Other tags in the docblock (`@param`, `@var`, `@OA\Get`) are left alone.

Other tools that read docblocks need to leave `@bidirekt` alone:

- `doctrine/annotations` (Symfony annotation routing, Doctrine ORM 2, serializer or validator annotations) stops at `@bidirekt` as an annotation that "was never imported". Ignore it with `\Doctrine\Common\Annotations\AnnotationReader::addGlobalIgnoredName('bidirekt');` in your app's bootstrap, or with `@IgnoreAnnotation("bidirekt")` in a class docblock.
- PHP-CS-Fixer's `phpdoc_to_comment` rule, in the `@Symfony` and `@PhpCsFixer` sets and in Pint's `symfony` preset, turns a `/** */` above a statement like `$app->get(...)` into a `/* */`, which `generate` does not read. Keep it with `'phpdoc_to_comment' => ['ignored_tags' => ['bidirekt']]` in `.php-cs-fixer.php`, or the same rule config in `pint.json`.

### Types

A field's type comes from PHP. The PHPDoc is read only where PHP can't say the type: the items of an `array`, and a property with no type. It is the property's `@var` or, on a promoted constructor parameter, its own `@var` or the constructor's `@param`, which must agree. A class name in the PHPDoc resolves through the file's `use` and namespace.

| PHP                                                    | Contract                                                      |
| ------------------------------------------------------ | ------------------------------------------------------------- |
| `string`, `bool`, `int`                                | `string`, `boolean`, `integer`                                |
| `float`, `int\|float`                                  | `number`; `float` only when declared                          |
| `?T`, `T\|null`                                        | `nullable: true`                                              |
| an enum with string values                             | `ref` to an `enum` schema named after it, its values quoted   |
| an enum with int values                                | `integer`                                                     |
| `list<T>`, `T[]` (PHPDoc)                              | `type: array`                                                 |
| `array<string, T>` (PHPDoc)                            | `type: map`                                                   |
| `list<T\|null>`, `array<string, T\|null>` (PHPDoc)     | `nullable: true` on the items or the values                   |
| a class with `#[BidirektSchema]` or `@bidirekt schema` | `ref`                                                         |
| `self`, `static`                                       | `ref` to the class itself                                     |
| a union of schema classes, `A\|B`, `A\|B\|null`        | `type: union`, with the keys from `union`                     |

`optional` comes only from a declaration: a PHP property is never optional. The keys of `union` match the type: one key for each schema of the union, and no key for a schema outside it; a union in the PHPDoc items or values takes `union` the same way.

A declared `type` is the whole type of the field, whatever PHP says, in the contract's words: a primitive (`string`, `integer`, `float`, `number`, `boolean`), the name of a schema, a class (`Owner::class`), `'array'` with `items`, or `'map'` with `values` (its keys are always strings). In an attribute, `items` and `values` take the same, or a PHP array with the keys `type`, `items`, `values`, `nullable` and `union` to nest, as in `items: ['union' => ['person' => Owner::class, 'shelter' => 'Shelter']]`, where a member is a class or a schema name. A short form like `'Owner[]'` or `'map<Owner>'` stops `generate`, which shows the long one. `optional` and `nullable` still come from PHP when not declared, and a declared one wins.

A type with no single contract type stops `generate` at its line, and you declare a `type` or a schema: `mixed`, `object`, `iterable`, `callable`, `void`, `never`, `null` alone, a union of primitives like `int|string`, an intersection `A&B`, a union of a class and a primitive, an `array` without the type of its items, `array<int, T>`, an array shape `array{...}`, an enum without values, a class that is not a schema, a date like `\DateTimeInterface`, a generic like `\ArrayObject<int, Pet>`, a property with neither a PHP type nor a `@var`, and a union of schemas without `union`.

### Names

A schema is named after its class or enum; `name` gives it another name in the contract. An anonymous class has no name to fall back to, so its schema needs one: `new #[BidirektSchema(name: 'Pet')] class { ... }`, or `/** @bidirekt schema name=Pet */` above `class`. A list body is named `<Schema>List` unless it declares its own name. A schema name in a handler or a tag is the name in the contract, which is not always the class's name.

An enum with string values used by a field is written as a schema; `#[BidirektSchema]` or `@bidirekt schema` on the enum names it and writes it even when no field uses it. On an enum without string values, an interface or a trait, it stops `generate`, as does `fields:` on an enum. A schema without a field stops `generate`. A schema class can reference a tagged class, and the other way round.

### Describe what the code does

The contract describes what the code does, not the type the code declares. The PHP type is where each field starts, and the field's options apply the rule: a declared `type`, `optional` or `nullable` wins over the PHP type. In the [Quickstart](#quickstart), `weight` declares `type: 'float'` because the code encodes it with `JSON_PRESERVE_ZERO_FRACTION`, and `nickname` declares `optional: true` because the code leaves it unset. In the same way:

- A `?string $name` whose database column allows `null`, but which the service always fills, declares `#[BidirektField(nullable: false)]` (`nullable=false`).
- A `\DateTimeImmutable $birthDate` that the body carries as a string declares `#[BidirektField(type: 'string')]` (`type=string`).

### What `json_encode` writes

PHP writes a body with `json_encode`, which changes three things the contract can't see:

- A `float` with no fraction, like `50.0`, is written as `50`, so a `float` field is `number`. With `JSON_PRESERVE_ZERO_FRACTION`, `json_encode` writes `50.0`; then declare the field `type: 'float'` (`type=float`), as `Pet::$weight` does.
- An empty PHP array, `[]`, is written as `[]` even when the field is a `map`, whose empty value is `{}`. Encode maps with `JSON_FORCE_OBJECT` or as a `stdClass`, or leave an empty map out of the body and declare the field `optional`.
- Laravel wraps an API Resource in `data` when it sends it. Describe the whole body, with `data` as a field (a handler schema `PetEnvelope` with `new BidirektField('data', type: 'Pet')`), or turn the wrapping off with `JsonResource::withoutWrapping()`.

### Inheritance and traits

A schema class takes the annotated fields of its parent classes, whether or not they are schemas, and of the traits it uses: the parent's fields first, then the class's in the order of the code, with a trait's at its `use` and the promoted parameters at the constructor. A field the class declares again replaces the parent's in the same place. A parent or a trait from a package is read from `vendor/`, so run `composer install` before `generate`.

### Errors

`generate` stops at a mistake and prints where it is and how to fix it. The ones you meet most:

- `cannot translate "mixed" to a contract type: declare it with #[BidirektField(type: …)]`
- `cannot translate "array" without the type of its items: write @var list<T> or array<string, T> in the docblock above the property and its attributes, or declare it with #[BidirektField(type: 'array', items: …)]`
- `cannot translate class "Address": add #[BidirektSchema] to it`
- `cannot translate union of classes "Owner|Shelter": declare each member's key with #[BidirektField(union: …)]`
- `schema "PetCollection" is not declared: reference a schema by its name in the contract`
- `schema name "Pet" is declared more than once: give each schema a distinct name`
- `method "get" must be uppercase: write "GET"`
- `#[BidirektProvider] and "@bidirekt provides GET /pets/*" are both on this handler: keep one`
- `#[BidirektField] resolves to "App\BidirektField", so it is not read: write use Bidirekt\PhpSdk\BidirektField`
- `cannot find class "Acme\Model\Entity" in the project, and the vendor folder is not installed: run composer install`

With tags, the hint names the tag instead, like `declare it with "@bidirekt field type=<type>"`.

## Docs

https://bidirekt.com/docs

## Development

Read [CONTRIBUTING.md](CONTRIBUTING.md). With PHP 8.0, the version CI uses, install the dependencies and run the checks from the repository root before you open a pull request:

```sh
composer install
composer check
composer lint
composer test
```

`composer test` also runs `tests/ReadmeTest.php`, which runs every example between `## Quickstart` and `## The generate command` through `generate` and compares the result with the YAML or the lines this README shows. An example is the fenced blocks right below a line with only the file's path in backticks, followed by a `yaml` block (`contracts/generated.yaml`) and a `text` block (stderr), or one of them; the blocks of one file repeated under a heading are the attribute and the tag forms of the same example.

## License

MIT, see [LICENSE](LICENSE).
