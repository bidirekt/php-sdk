# Bidirekt PHP SDK

[![CI](https://img.shields.io/github/actions/workflow/status/bidirekt/php-sdk/ci.yml?branch=main&label=CI)](https://github.com/bidirekt/php-sdk/actions/workflows/ci.yml) [![License](https://img.shields.io/github/license/bidirekt/php-sdk)](LICENSE)

In Bidirekt, each side of an integration declares in a contract file what it provides and what it consumes. This SDK writes that file from the code of a service on PHP 8.0 or later: you annotate the schemas and the handlers that provide or consume each resource, with attributes or with `@bidirekt` tags in docblocks, run `generate`, and get `contracts/generated.yaml`, ready for `bidirekt validate` and `bidirekt publish`.

`contracts/generated.yaml` is the service's only contract file: once a service adopts the SDK, every resource and schema it provides or consumes is annotated in its code.

## Installation

The SDK needs PHP 8.0 or later and Composer 2.2 or later. Install it as a dev dependency:

```sh
composer require --dev bidirekt/php-sdk
```

The SDK needs `nikic/php-parser` 5.6.2 or later to read PHP 8.4 and 8.5 code. If Composer says your lock file fixes an older version (a Laravel project locks it through `psy/psysh`), run `composer require --dev bidirekt/php-sdk -W` so Composer may update it.

It is one package: the four attributes your code uses and the `generate` command. An attribute runs nothing: PHP loads its class only when code calls `newInstance()` on it, and `generate` reads the attributes from the source instead. So a production install with `composer install --no-dev`, which leaves the package out, still runs your app.

`generate` reads your code and never runs it, so it reads code of any PHP version. Two forms need a newer PHP to run your app, not to run `generate`:

- `new BidirektField(...)` inside `fields:` needs PHP 8.1, as does an enum. On PHP 8.0, declare a schema without a class with tags, see [Schemas without a class](#schemas-without-a-class).
- `Enum::Case->value` as an attribute argument needs PHP 8.2.

## Quickstart

There are two ways to annotate, and each covers schemas, the resources a service provides and the ones it consumes: attributes, or `@bidirekt` tags in `/** */` docblocks, for code that can't use attributes or prefers docblocks. Both examples below write the same contract.

A field without an annotation stays out of the contract. Its type comes from PHP, and from the PHPDoc where PHP can't say it (the items of an array); declare `type` when neither says it, see [Declared types](#declared-types).

### With attributes

Mark each schema with `#[BidirektSchema]` and each field of the contract with `#[BidirektField]`, on a property or a promoted constructor parameter:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    public function __construct(
        #[BidirektField] public int $id,
        #[BidirektField] public string $name,
        #[BidirektField] public ?string $birthDate,
        public string $internalNotes = '',
    ) {}
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
    #[BidirektField]
    public string $name;
}
```

`src/NewCharge.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class NewCharge
{
    public function __construct(
        #[BidirektField] public int $amount,
    ) {}
}
```

`src/Charge.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Charge
{
    #[BidirektField]
    public string $id;
}
```

Declare what a route provides with `#[BidirektProvider]` on its handler: a method, a function, or the closure you pass to the route (Slim, for example). A body is a schema class, `null` for no body, `[Pet::class]` for a list (named `PetList`), or `['list' => Pet::class, 'name' => 'PetCollection']` for a list with its own name:

`public/index.php`
```php
<?php

use App\NewPet;
use App\Pet;
use Bidirekt\PhpSdk\BidirektProvider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$pets = [new Pet(1, 'Rex', null)];

function json(Response $response, mixed $body, int $status = 200): Response
{
    $response->getBody()->write((string) json_encode($body));

    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

$app->get(
    '/pets',
    #[BidirektProvider(method: 'GET', path: '/pets', responses: [200 => [Pet::class]])]
    fn (Request $request, Response $response): Response => json($response, $pets),
);

$app->get(
    '/pets/{id}',
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    function (Request $request, Response $response, array $args) use ($pets): Response {
        foreach ($pets as $pet) {
            if ($pet->id === (int) $args['id']) {
                return json($response, $pet);
            }
        }

        return $response->withStatus(404);
    },
);

$app->post(
    '/pets',
    #[BidirektProvider(method: 'POST', path: '/pets', request: NewPet::class, responses: [201 => Pet::class, 400 => null])]
    function (Request $request, Response $response) use ($pets): Response {
        $body = $request->getParsedBody();
        if (!is_array($body) || !is_string($body['name'] ?? null)) {
            return $response->withStatus(400);
        }

        return json($response, new Pet(count($pets) + 1, $body['name'], null), 201);
    },
);

$app->run();
```

And what a client consumes with `#[BidirektConsumer]`:

`src/PaymentsClient.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektConsumer;
use GuzzleHttp\Client;

final class PaymentsClient
{
    public function __construct(private Client $http) {}

    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        request: NewCharge::class,
        responses: [201 => Charge::class, 402 => null],
    )]
    public function charge(NewCharge $charge): Charge
    {
        $response = $this->http->post('/charges', ['json' => $charge, 'http_errors' => false]);
        if ($response->getStatusCode() === 402) {
            throw new \RuntimeException('payment required');
        }
        $charge = new Charge();
        $charge->id = json_decode((string) $response->getBody(), true)['id'];

        return $charge;
    }
}
```

### With tags

Mark each schema with `@bidirekt schema` in the docblock of its class and each field of the contract with `@bidirekt field`; the PHP type or the `@var` beside it gives the field's type. `field` takes `type=`, `items=`, `values=`, `name=`, `optional=`, `nullable=`, `description="..."` and `union=`. Arguments are separated by spaces; only a `description` value can be quoted to hold spaces, and only a `union=` value grouped in `{...}`:

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public int $id;

    /** @bidirekt field */
    public string $name;

    /**
     * @bidirekt field
     * @var string|null
     */
    public $birthDate;

    public string $internalNotes = '';
}
```

`src/NewPet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class NewPet
{
    /** @bidirekt field */
    public string $name;
}
```

`src/NewCharge.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class NewCharge
{
    /** @bidirekt field */
    public int $amount;
}
```

`src/Charge.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Charge
{
    /** @bidirekt field */
    public string $id;
}
```

Declare what a route provides with `@bidirekt provides <METHOD> <path>` in the docblock above it, then its `@bidirekt request [schema]` and each `@bidirekt response <status> [schema]`. A tag names a schema by its name in the contract; `Pet[]` is a list (named `PetList`) and `Pet[] as PetCollection` a list with its own name:

`public/index.php`
```php
<?php

use App\Pet;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$rex = new Pet();
$rex->id = 1;
$rex->name = 'Rex';
$rex->birthDate = null;
$pets = [$rex];

function json(Response $response, mixed $body, int $status = 200): Response
{
    $response->getBody()->write((string) json_encode($body));

    return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

/**
 * @bidirekt provides GET /pets
 * @bidirekt response 200 Pet[]
 */
$app->get('/pets', fn (Request $request, Response $response): Response => json($response, $pets));

/**
 * @bidirekt provides GET /pets/*
 * @bidirekt response 200 Pet
 * @bidirekt response 404
 */
$app->get('/pets/{id}', function (Request $request, Response $response, array $args) use ($pets): Response {
    foreach ($pets as $pet) {
        if ($pet->id === (int) $args['id']) {
            return json($response, $pet);
        }
    }

    return $response->withStatus(404);
});

/**
 * @bidirekt provides POST /pets
 * @bidirekt request NewPet
 * @bidirekt response 201 Pet
 * @bidirekt response 400
 */
$app->post('/pets', function (Request $request, Response $response) use ($pets): Response {
    $body = $request->getParsedBody();
    if (!is_array($body) || !is_string($body['name'] ?? null)) {
        return $response->withStatus(400);
    }
    $pet = new Pet();
    $pet->id = count($pets) + 1;
    $pet->name = $body['name'];
    $pet->birthDate = null;

    return json($response, $pet, 201);
});

$app->run();
```

And what a function or method consumes with `@bidirekt consumes <provider> <METHOD> <path>`:

`src/PaymentsClient.php`
```php
<?php

namespace App;

use GuzzleHttp\Client;

final class PaymentsClient
{
    public function __construct(private Client $http) {}

    /**
     * @bidirekt consumes payments_api POST /charges
     * @bidirekt request NewCharge
     * @bidirekt response 201 Charge
     * @bidirekt response 402
     */
    public function charge(NewCharge $charge): Charge
    {
        $response = $this->http->post('/charges', ['json' => $charge, 'http_errors' => false]);
        if ($response->getStatusCode() === 402) {
            throw new \RuntimeException('payment required');
        }
        $charge = new Charge();
        $charge->id = json_decode((string) $response->getBody(), true)['id'];

        return $charge;
    }
}
```

### Generate the contract

Run `generate` in the project folder, the one with `composer.json`:

```sh
vendor/bin/php-sdk generate
```

Either example writes the same `contracts/generated.yaml`:

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets:
      get:
        responses:
          200: PetList
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
  payments_api:
    rest:
      /charges:
        post:
          request: NewCharge
          responses:
            201: Charge
            402: ~
schemas:
  Charge:
    type: object
    properties:
      id:
        type: string
  NewCharge:
    type: object
    properties:
      amount:
        type: integer
  NewPet:
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
      birthDate:
        type: string
        nullable: true
  PetList:
    type: array
    items:
      ref: Pet
```

Commit it with the code and regenerate it whenever an annotation or an annotated type changes. Then the flow is the same as with a hand-written contract file:

```sh
vendor/bin/php-sdk generate
bidirekt validate contracts/generated.yaml --participant petstore_api --environment production
bidirekt publish contracts/generated.yaml --participant petstore_api --version 1.4.0
```

In CI, `vendor/bin/php-sdk generate --check` fails the build when the committed file is out of date. Never edit `contracts/generated.yaml`: the next `generate` writes over it.

### Schemas without a class

A body with no class to annotate, like an array a handler builds or a Laravel API Resource, declares its schema with its fields in the attribute: `#[BidirektSchema(name: ..., fields: [...])]` on the handler, where `name` is required, and each field is a `new BidirektField('<name>', type: ...)` that declares `type` or `union`. With tags, `@bidirekt schema <Name>` in the handler's docblock opens a schema and each following `@bidirekt field <name> type=...` is a field of it, until the next `schema` or the end of the docblock. These schemas belong to the whole contract, so other handlers and fields reference them by name.

`new` in an attribute needs PHP 8.1; on PHP 8.0, use the tags.

`public/index.php`
```php
<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

$app->get(
    '/health',
    #[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])]
    #[BidirektSchema(name: 'Health', fields: [
        new BidirektField('status', type: 'string'),
        new BidirektField('uptime', type: 'integer'),
        new BidirektField('checks', type: 'array', items: 'HealthCheck'),
    ])]
    #[BidirektSchema(name: 'HealthCheck', fields: [
        new BidirektField('name', type: 'string'),
        new BidirektField('ok', type: 'boolean'),
        new BidirektField('error', type: 'string', nullable: true),
    ])]
    function (Request $request, Response $response): Response {
        $response->getBody()->write((string) json_encode([
            'status' => 'ok',
            'uptime' => 42,
            'checks' => [['name' => 'database', 'ok' => true, 'error' => null]],
        ]));

        return $response;
    },
);
```

`public/index.php`
```php
<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

$app = AppFactory::create();

/**
 * @bidirekt provides GET /health
 * @bidirekt response 200 Health
 *
 * @bidirekt schema Health
 * @bidirekt field status type=string
 * @bidirekt field uptime type=integer
 * @bidirekt field checks type=array items=HealthCheck
 *
 * @bidirekt schema HealthCheck
 * @bidirekt field name type=string
 * @bidirekt field ok type=boolean
 * @bidirekt field error type=string nullable=true
 */
$app->get('/health', function (Request $request, Response $response): Response {
    $response->getBody()->write((string) json_encode([
        'status' => 'ok',
        'uptime' => 42,
        'checks' => [['name' => 'database', 'ok' => true, 'error' => null]],
    ]));

    return $response;
});
```

`generate` writes:

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /health:
      get:
        responses:
          200: Health
schemas:
  Health:
    type: object
    properties:
      status:
        type: string
      uptime:
        type: integer
      checks:
        type: array
        items:
          ref: HealthCheck
  HealthCheck:
    type: object
    properties:
      name:
        type: string
      ok:
        type: boolean
      error:
        type: string
        nullable: true
```

On a class, `fields:` declares the fields of a body the class builds by hand. Without `name`, the schema is named after the class, and the fields come after the ones annotated in the class or its parents:

`src/PetSummary.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [
    new BidirektField('name', type: 'string'),
    new BidirektField('tags', type: 'array', items: 'string', optional: true),
])]
final class PetSummary implements \JsonSerializable
{
    public function __construct(
        #[BidirektField] public int $id,
        private array $row,
    ) {}

    public function jsonSerialize(): array
    {
        $body = ['id' => $this->id, 'name' => $this->row['name']];
        if ($this->row['tags'] !== []) {
            $body['tags'] = $this->row['tags'];
        }

        return $body;
    }
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  PetSummary:
    type: object
    properties:
      id:
        type: integer
      name:
        type: string
      tags:
        type: array
        items:
          type: string
        optional: true
```

A Laravel API Resource takes `fields:` the same way. Its parent, `JsonResource`, is read from `vendor/`, so run `composer install` before `generate`. Laravel wraps the resource in `data` when it sends it, see [What `json_encode` writes](#what-json_encode-writes).

A schema with no field stops `generate`, in both forms:

`public/index.php`
```php
<?php

use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektProvider(method: 'GET', path: '/health', responses: [200 => 'Health'])]
#[BidirektSchema(name: 'Health', fields: [])]
function health(): array
{
    return [];
}

/**
 * @bidirekt provides GET /ready
 * @bidirekt response 200 Ready
 *
 * @bidirekt schema Ready
 */
function ready(): array
{
    return [];
}
```

```text
public/index.php:7 schema "Health" has no field: declare at least one field in "fields"
public/index.php:17 schema "Ready" has no field: annotate at least one field with "@bidirekt field"
```

## Reference

Every example below is a whole project: `generate` run on it alone writes the YAML shown, or prints the lines shown and exits with 1. Where a feature has both forms, the attribute example and the tag example write the same YAML. To stay short, an example keeps several classes in one file; `generate` reads every `.php` file of the project, whatever it declares.

`generate` recognizes an attribute by its full name, `Bidirekt\PhpSdk\BidirektField` and the others, after it resolves the file's `use`, alias and namespace. An attribute with the same short name that resolves to another namespace, because the `use` is missing, is not read, and `generate` warns:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;

    #[\Bidirekt\PhpSdk\BidirektField]
    public string $breed;
}
```

```text
src/Pet.php:10 warning: #[BidirektField] resolves to "App\BidirektField", so it is not read: write use Bidirekt\PhpSdk\BidirektField
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Pet:
    type: object
    properties:
      breed:
        type: string
```

### Fields

`#[BidirektField(...)]` and `@bidirekt field key=value ...` take the same options:

| Attribute                                                       | Tag                                          | What it declares                                                                      |
| --------------------------------------------------------------- | -------------------------------------------- | ------------------------------------------------------------------------------------- |
| `name: 'birth_date'`                                            | `name=birth_date`                            | the field's name in the contract; without it, the property's name                     |
| `type: 'integer'`                                               | `type=integer`                               | the whole type of the field, see [Declared types](#declared-types)                    |
| `items: 'Pet'`                                                  | `items=Pet`                                  | the type of the items of `type: 'array'`                                              |
| `values: 'string'`                                              | `values=string`                              | the type of the values of `type: 'map'`                                               |
| `optional: true`                                                | `optional=true`                              | whether the field can be absent; without it, it can't                                 |
| `nullable: true`                                                | `nullable=true`                              | whether the field can be `null`; without it, from `?T` or `null` in the type          |
| `description: 'Shown on the card'`                              | `description="Shown on the card"`            | the field's description; only a declared one, a plain docblock is not                 |
| `union: ['card' => CardPayment::class, 'pix' => PixPayment::class]` | `union={card: CardPayment, pix: PixPayment}` | the key of each member of a union of schemas, see [Unions](#unions)                   |

Written without names, the arguments of `#[BidirektField]` follow that order: `#[BidirektField('birth_date')]` is `name: 'birth_date'`. A property without an annotation stays out of the contract, whatever its visibility.

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(name: 'birth_date', description: 'Day the pet was born')]
    public string $birthDate;

    #[BidirektField(optional: true, nullable: true)]
    public string $nickname;

    #[BidirektField(nullable: false)]
    public ?string $breed;

    public string $internalNotes;
}
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field name=birth_date description="Day the pet was born" */
    public string $birthDate;

    /** @bidirekt field optional=true nullable=true */
    public string $nickname;

    /** @bidirekt field nullable=false */
    public ?string $breed;

    public string $internalNotes;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Pet:
    type: object
    properties:
      birth_date:
        type: string
        description: Day the pet was born
      nickname:
        type: string
        optional: true
        nullable: true
      breed:
        type: string
```

A promoted constructor parameter is a field like a property. A parameter that is not promoted, or a `static` property, is not a field: an annotation on it is not read, and `generate` warns:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public static string $species = 'dog';

    public function __construct(
        #[BidirektField] public string $name,
        #[BidirektField] string $nickname,
    ) {}
}

/** @bidirekt schema */
final class Toy
{
    /** @bidirekt field */
    public static string $brand = 'acme';

    public function __construct(
        /** @bidirekt field */
        public string $name,
        /** @bidirekt field */
        string $color,
    ) {}
}
```

```text
src/Pet.php:11 warning: #[BidirektField] is on a static property, so it is not read: put it on a property
src/Pet.php:16 warning: #[BidirektField] is on a parameter that is not promoted, so it is not read: put it on a property or a promoted constructor parameter
src/Pet.php:23 warning: "@bidirekt field" is above a static property, so it is not read: put it above a property
src/Pet.php:29 warning: "@bidirekt field" is above a parameter that is not promoted, so it is not read: put it above a property or a promoted constructor parameter
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Pet:
    type: object
    properties:
      name:
        type: string
  Toy:
    type: object
    properties:
      name:
        type: string
```

### Types

A field's type comes from PHP. The PHPDoc is read only where PHP can't say the type: the items of an array, and a property with no type.

| PHP                                                              | Contract                                                   |
| ---------------------------------------------------------------- | ---------------------------------------------------------- |
| `string`, `bool`, `int`                                          | `string`, `boolean`, `integer`                             |
| `float`, `int\|float`                                            | `number`; `float` only when declared                       |
| `?T`, `T\|null`                                                  | `nullable: true`                                           |
| an enum with string values                                       | `ref` to an `enum` schema named after it, with its values  |
| an enum with int values                                          | `integer`                                                  |
| `list<T>`, `T[]` (PHPDoc)                                        | `type: array`                                              |
| `array<string, T>` (PHPDoc)                                      | `type: map`                                                |
| `list<T\|null>`, `array<string, T\|null>` (PHPDoc)               | `nullable: true` on the items or values                    |
| a class with `#[BidirektSchema]` or `@bidirekt schema`           | `ref`                                                      |
| `self`, `static`                                                 | `ref` to the class itself                                  |
| a union of classes, `A\|B`, `A\|B\|null`                         | `type: union`, with the keys from `union`                  |

`optional` comes only from a declaration: a PHP property is never optional.

#### Primitives and `null`

`float` is `number` because `json_encode` writes `50.0` as `50`, and the contract needs `number` for a value that can be a whole float, see [What `json_encode` writes](#what-json_encode-writes):

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;

    #[BidirektField]
    public bool $vaccinated;

    #[BidirektField]
    public int $age;

    #[BidirektField]
    public float $weight;

    #[BidirektField]
    public int|float $height;

    #[BidirektField]
    public ?string $birthDate;

    #[BidirektField]
    public string|null $color;
}
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public string $name;

    /** @bidirekt field */
    public bool $vaccinated;

    /** @bidirekt field */
    public int $age;

    /** @bidirekt field */
    public float $weight;

    /** @bidirekt field */
    public int|float $height;

    /** @bidirekt field */
    public ?string $birthDate;

    /** @bidirekt field */
    public string|null $color;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Pet:
    type: object
    properties:
      name:
        type: string
      vaccinated:
        type: boolean
      age:
        type: integer
      weight:
        type: number
      height:
        type: number
      birthDate:
        type: string
        nullable: true
      color:
        type: string
        nullable: true
```

#### Enums

An enum with string values is written as a schema named after it, with its values as members, always quoted. An enum with int values is an `integer`:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum PetStatus: string
{
    case Available = 'available';
    case OnHold = 'on_hold';
}

enum Priority: int
{
    case Low = 1;
    case High = 2;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetStatus $status;

    #[BidirektField]
    public ?PetStatus $previousStatus;

    #[BidirektField]
    public Priority $priority;
}
```

`src/Pet.php`
```php
<?php

namespace App;

enum PetStatus: string
{
    case Available = 'available';
    case OnHold = 'on_hold';
}

enum Priority: int
{
    case Low = 1;
    case High = 2;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public PetStatus $status;

    /** @bidirekt field */
    public ?PetStatus $previousStatus;

    /** @bidirekt field */
    public Priority $priority;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Pet:
    type: object
    properties:
      status:
        ref: PetStatus
      previousStatus:
        ref: PetStatus
        nullable: true
      priority:
        type: integer
  PetStatus:
    type: enum
    members: ["available", "on_hold"]
```

To name an enum's schema, or to write it without a field that uses it, see [Enums](#enums-1) under Schemas.

#### Lists, maps and references

An `array` needs the type of its items, from the PHPDoc or from a [declared type](#declared-types). The PHPDoc is the `@var` of the property or, on a promoted constructor parameter, its own `@var` or the constructor's `@param`; `list<T>` and `T[]` are lists, `array<string, T>` is a map. A class name in the PHPDoc resolves through the file's `use` and namespace, like in the code:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Pet
{
    /** @var list<string> */
    #[BidirektField]
    public array $tags;

    /** @var list<int|null> */
    #[BidirektField]
    public array $scores;

    /** @var array<string, string|null> */
    #[BidirektField]
    public array $notes;

    #[BidirektField]
    public Owner $owner;

    /** @var Owner[] */
    #[BidirektField]
    public array $formerOwners;

    #[BidirektField]
    public ?self $mother;

    /**
     * @param array<string, Owner> $ownersByCity
     */
    public function __construct(
        #[BidirektField] public array $ownersByCity,
    ) {}
}
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Owner
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Pet
{
    /**
     * @bidirekt field
     * @var list<string>
     */
    public array $tags;

    /**
     * @bidirekt field
     * @var list<int|null>
     */
    public array $scores;

    /**
     * @bidirekt field
     * @var array<string, string|null>
     */
    public array $notes;

    /** @bidirekt field */
    public Owner $owner;

    /**
     * @bidirekt field
     * @var Owner[]
     */
    public array $formerOwners;

    /** @bidirekt field */
    public ?self $mother;

    /**
     * @param array<string, Owner> $ownersByCity
     */
    public function __construct(
        /** @bidirekt field */
        public array $ownersByCity,
    ) {}
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Owner:
    type: object
    properties:
      name:
        type: string
  Pet:
    type: object
    properties:
      tags:
        type: array
        items:
          type: string
      scores:
        type: array
        items:
          type: integer
          nullable: true
      notes:
        type: map
        values:
          type: string
          nullable: true
      owner:
        ref: Owner
      formerOwners:
        type: array
        items:
          ref: Owner
      mother:
        ref: Pet
        nullable: true
      ownersByCity:
        type: map
        values:
          ref: Owner
```

A property with no PHP type takes its whole type from its `@var`. With a PHP type, the `@var` only adds the type of the items of an array. A `@var` and a `@param` on the same promoted parameter must say the same type:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var string */
    #[BidirektField]
    public $name;

    /** @var ?string */
    #[BidirektField]
    public $nickname;

    /**
     * @param list<string> $tags
     */
    public function __construct(
        /** @var list<int> */
        #[BidirektField] public array $tags,
    ) {}
}
```

```text
src/Pet.php:24 "tags" has a @var "list<int>" and a @param "list<string>" that differ: keep one of them
```

A schema class can reference a tagged class, and the other way round.

#### Declared types

A declared `type` is the whole type of the field, whatever PHP says, written with the contract's words: a primitive (`string`, `integer`, `float`, `number`, `boolean`), the name of a schema in the contract, a class (`Owner::class`), `'array'` with `items`, or `'map'` with `values` (its keys are always strings). In an attribute, `items` and `values` take the same, or a PHP array with the keys `type`, `items`, `values`, `nullable` and `union` to nest. A tag doesn't nest: `items=` and `values=` take a primitive or a schema name, and a list of lists in a tag needs a schema or the PHPDoc. `optional` and `nullable` still come from the PHP type when not declared, and a declared one wins:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'float')]
    public float $weight;

    #[BidirektField(type: 'string')]
    public ?\DateTimeImmutable $bornAt;

    #[BidirektField(type: 'array', items: 'integer')]
    public array $scores;

    #[BidirektField(type: 'map', values: 'float')]
    public array $weights;

    #[BidirektField(type: 'array', items: ['type' => 'array', 'items' => 'integer'])]
    public array $grid;

    #[BidirektField(type: 'map', values: ['type' => 'string', 'nullable' => true])]
    public array $labels;

    #[BidirektField(type: Owner::class)]
    public object $keeper;

    #[BidirektField(type: 'array', items: 'Owner', optional: true)]
    public array $volunteers;
}
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Owner
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field type=float */
    public float $weight;

    /** @bidirekt field type=string */
    public ?\DateTimeImmutable $bornAt;

    /** @bidirekt field type=array items=integer */
    public array $scores;

    /** @bidirekt field type=map values=float */
    public array $weights;

    /**
     * @bidirekt field
     * @var list<list<int>>
     */
    public array $grid;

    /**
     * @bidirekt field
     * @var array<string, string|null>
     */
    public array $labels;

    /** @bidirekt field type=Owner */
    public object $keeper;

    /** @bidirekt field type=array items=Owner optional=true */
    public array $volunteers;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Owner:
    type: object
    properties:
      name:
        type: string
  Pet:
    type: object
    properties:
      weight:
        type: float
      bornAt:
        type: string
        nullable: true
      scores:
        type: array
        items:
          type: integer
      weights:
        type: map
        values:
          type: float
      grid:
        type: array
        items:
          type: array
          items:
            type: integer
      labels:
        type: map
        values:
          type: string
          nullable: true
      keeper:
        ref: Owner
      volunteers:
        type: array
        items:
          ref: Owner
        optional: true
```

A short form like `'Owner[]'` or `'map<Owner>'` is not a declared type; `generate` stops and shows the long one:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Owner
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField(type: 'Owner[]')]
    public array $owners;

    #[BidirektField(type: 'map<Owner>')]
    public array $ownersByCity;
}
```

```text
src/Pet.php:18 cannot read type "Owner[]": write type: 'array', items: 'Owner'
src/Pet.php:21 cannot read type "map<Owner>": write type: 'map', values: 'Owner'
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Owner
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field type=Owner[] */
    public array $owners;

    /** @bidirekt field type=map<Owner> */
    public array $ownersByCity;
}
```

```text
src/Pet.php:15 cannot read "type=Owner[]" in "@bidirekt field": write type=array items=Owner
src/Pet.php:18 cannot read "type=map<Owner>" in "@bidirekt field": write type=map values=Owner
```

#### Types `generate` can't translate

A type with no single contract type stops `generate` at its line. Declare a `type`, or declare a schema and use it as the type:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum Size
{
    case Small;
    case Large;
}

final class Address
{
    public string $street;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public int|string $code;

    #[BidirektField]
    public mixed $extra;

    #[BidirektField]
    public object $data;

    #[BidirektField]
    public iterable $toys;

    #[BidirektField]
    public array $tags;

    /** @var array<int, string> */
    #[BidirektField]
    public array $notes;

    /** @var array{street: string} */
    #[BidirektField]
    public array $location;

    #[BidirektField]
    public Size $size;

    #[BidirektField]
    public \DateTimeInterface $bornAt;

    #[BidirektField]
    public Address $address;

    /** @var \ArrayObject<int, Address> */
    #[BidirektField]
    public $homes;

    #[BidirektField]
    public $nickname;
}
```

```text
src/Pet.php:23 cannot translate "int|string" to a contract type: declare it with #[BidirektField(type: …)]
src/Pet.php:26 cannot translate "mixed" to a contract type: declare it with #[BidirektField(type: …)]
src/Pet.php:29 cannot translate "object" to a contract type: declare it with #[BidirektField(type: …)]
src/Pet.php:32 cannot translate "iterable" to a contract type: declare it with #[BidirektField(type: …)]
src/Pet.php:35 cannot translate "array" without the type of its items: write @var list<T> or array<string, T> in the docblock above the property and its attributes, or declare it with #[BidirektField(type: 'array', items: …)]
src/Pet.php:39 cannot translate "array<int, string>": write list<string> for a list or array<string, string> for a map
src/Pet.php:43 cannot translate array shape "array{street: string}": declare a class with #[BidirektSchema] and use it as the type
src/Pet.php:46 cannot translate enum "Size", which has no values: give its cases string values
src/Pet.php:49 cannot translate "\DateTimeInterface" to a contract type: declare it with #[BidirektField(type: …)]
src/Pet.php:52 cannot translate class "Address": add #[BidirektSchema] to it
src/Pet.php:56 cannot translate generic type "\ArrayObject<int, Address>": declare a non-generic class with #[BidirektSchema] and use it as the type
src/Pet.php:59 cannot read the type of "nickname": write its type or declare it with #[BidirektField(type: …)]
```

`src/Pet.php`
```php
<?php

namespace App;

enum Size
{
    case Small;
    case Large;
}

final class Address
{
    public string $street;
}

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public int|string $code;

    /** @bidirekt field */
    public mixed $extra;

    /** @bidirekt field */
    public object $data;

    /** @bidirekt field */
    public iterable $toys;

    /** @bidirekt field */
    public array $tags;

    /**
     * @bidirekt field
     * @var array<int, string>
     */
    public array $notes;

    /**
     * @bidirekt field
     * @var array{street: string}
     */
    public array $location;

    /** @bidirekt field */
    public Size $size;

    /** @bidirekt field */
    public \DateTimeInterface $bornAt;

    /** @bidirekt field */
    public Address $address;

    /**
     * @bidirekt field
     * @var \ArrayObject<int, Address>
     */
    public $homes;

    /** @bidirekt field */
    public $nickname;
}
```

```text
src/Pet.php:20 cannot translate "int|string" to a contract type: declare it with "@bidirekt field type=<type>"
src/Pet.php:23 cannot translate "mixed" to a contract type: declare it with "@bidirekt field type=<type>"
src/Pet.php:26 cannot translate "object" to a contract type: declare it with "@bidirekt field type=<type>"
src/Pet.php:29 cannot translate "iterable" to a contract type: declare it with "@bidirekt field type=<type>"
src/Pet.php:32 cannot translate "array" without the type of its items: write @var list<T> or array<string, T> in the docblock above the property and its attributes, or declare it with "@bidirekt field type=array items=<type>"
src/Pet.php:38 cannot translate "array<int, string>": write list<string> for a list or array<string, string> for a map
src/Pet.php:44 cannot translate array shape "array{street: string}": declare a class with "@bidirekt schema" and use it as the type
src/Pet.php:47 cannot translate enum "Size", which has no values: give its cases string values
src/Pet.php:50 cannot translate "\DateTimeInterface" to a contract type: declare it with "@bidirekt field type=<type>"
src/Pet.php:53 cannot translate class "Address": add "@bidirekt schema" above it
src/Pet.php:59 cannot translate generic type "\ArrayObject<int, Address>": declare a non-generic class with "@bidirekt schema" and use it as the type
src/Pet.php:62 cannot read the type of "nickname": write its type or declare it with "@bidirekt field type=<type>"
```

Any other type stops `generate` the same way: `callable`, `void`, `never`, `null` alone, an intersection `A&B`, a union of a class and a primitive. A union of schemas without keys is an error too, see [Unions](#unions).

### Describe what the code does

The contract describes what the code does, not the type the code declares. The PHP type is where each field starts, and the field's options apply the rule: a declared `type`, `optional` or `nullable` wins over the PHP type.

`Pet::$name` is `?string` because the database column allows `null`, but `petstore_api` always fills it, so the field declares `nullable: false`. `Pet::$birthDate` is a `DateTimeImmutable` in the code and a string in the body. `Pet::$photoUrls` is left out of the body until the pet has photos:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(nullable: false)]
    public ?string $name;

    #[BidirektField(type: 'string')]
    public \DateTimeImmutable $birthDate;

    /** @var list<string> */
    #[BidirektField(optional: true)]
    public array $photoUrls;
}
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field nullable=false */
    public ?string $name;

    /** @bidirekt field type=string */
    public \DateTimeImmutable $birthDate;

    /**
     * @bidirekt field optional=true
     * @var list<string>
     */
    public array $photoUrls;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Pet:
    type: object
    properties:
      name:
        type: string
      birthDate:
        type: string
      photoUrls:
        type: array
        items:
          type: string
        optional: true
```

### What `json_encode` writes

PHP writes a body with `json_encode`, which changes three things the contract can't see:

- A `float` with no fraction, like `50.0`, is written as `50`, so a `float` field is `number`. With `JSON_PRESERVE_ZERO_FRACTION`, `json_encode` writes `50.0`; then declare the field `type: 'float'` (or `type=float`), as in [Declared types](#declared-types).
- An empty PHP array, `[]`, is written as `[]` even when the field is a `map`, whose empty value is `{}`. Encode maps with `JSON_FORCE_OBJECT` or as a `stdClass`, or leave an empty map out of the body and declare the field `optional`.
- Laravel wraps an API Resource in `data` when it sends it. Describe the whole body, with `data` as a field, or turn the wrapping off with `JsonResource::withoutWrapping()`:

`app/Http/Controllers/PetController.php`
```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\PetResource;
use App\Models\Pet;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

final class PetController
{
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => 'PetEnvelope', 404 => null])]
    #[BidirektSchema(name: 'PetEnvelope', fields: [
        new BidirektField('data', type: 'Pet'),
    ])]
    #[BidirektSchema(name: 'Pet', fields: [
        new BidirektField('id', type: 'integer'),
        new BidirektField('name', type: 'string'),
    ])]
    public function show(Pet $pet): PetResource
    {
        return new PetResource($pet);
    }
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets/*:
      get:
        responses:
          200: PetEnvelope
          404: ~
schemas:
  Pet:
    type: object
    properties:
      id:
        type: integer
      name:
        type: string
  PetEnvelope:
    type: object
    properties:
      data:
        ref: Pet
```

### Schemas

#### Names

A schema is named after its class; `name` gives it another name in the contract:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetResponse')]
final class Pet
{
    #[BidirektField]
    public string $name;
}
```

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema name=PetResponse */
final class Pet
{
    /** @bidirekt field */
    public string $name;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  PetResponse:
    type: object
    properties:
      name:
        type: string
```

An anonymous class has no name to fall back to, so its schema needs one: `new #[BidirektSchema(name: 'Pet')] class { … }`, or `/** @bidirekt schema name=Pet */` above `class`.

Every schema is written, even one no handler references. Two schemas with the same name in the contract stop `generate`, also two classes with the same short name in different namespaces; give one of them a `name`:

`src/Shop/Pet.php`
```php
<?php

namespace App\Shop;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}
```

`src/Billing/Pet.php`
```php
<?php

namespace App\Billing;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public int $price;
}
```

```text
src/Billing/Pet.php:9 schema name "Pet" is declared more than once: give each schema a distinct name
src/Shop/Pet.php:9 schema name "Pet" is declared more than once: give each schema a distinct name
```

A schema without a field stops `generate`:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    public string $name;
}

/** @bidirekt schema */
final class Owner
{
    public string $name;
}
```

```text
src/Pet.php:8 schema "Pet" has no field: annotate at least one field with #[BidirektField]
src/Pet.php:14 schema "Owner" has no field: annotate at least one field with "@bidirekt field"
```

`#[BidirektSchema]` and `@bidirekt schema` go on a class, or on an enum with string values; on an interface or a trait they stop `generate`.

#### Enums

An enum with string values used by a field is written as a schema named after it. `#[BidirektSchema]` or `@bidirekt schema` on the enum gives it another name, and writes it even when no field uses it, so handlers and tags can reference it (`@bidirekt response 200 PetStatus`, `type=PetStatus`). `fields:` on an enum stops `generate`: its members are its values.

`src/PetStatus.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetStatus')]
enum Status: string
{
    case Available = 'available';
    case Sold = 'sold';
}

#[BidirektSchema]
enum Size: string
{
    case Small = 'small';
    case Large = 'large';
}
```

`src/PetStatus.php`
```php
<?php

namespace App;

/** @bidirekt schema name=PetStatus */
enum Status: string
{
    case Available = 'available';
    case Sold = 'sold';
}

/** @bidirekt schema */
enum Size: string
{
    case Small = 'small';
    case Large = 'large';
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  PetStatus:
    type: enum
    members: ["available", "sold"]
  Size:
    type: enum
    members: ["small", "large"]
```

An enum without string values can't be a schema:

`src/Priority.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
enum Priority: int
{
    case Low = 1;
    case High = 2;
}

/** @bidirekt schema */
enum Size
{
    case Small;
    case Large;
}
```

```text
src/Priority.php:8 #[BidirektSchema] is on enum "Priority", whose values are not strings: give its cases string values
src/Priority.php:14 "@bidirekt schema" is above enum "Size", which has no values: give its cases string values
```

#### Inheritance and traits

A schema class takes the annotated fields of its parent classes, whether or not they are schemas, and of the traits it uses. The parent's fields come first; then the class's, in the order of the code, with a trait's at its `use` and the promoted parameters at the constructor. A field the class declares again replaces the parent's in the same place:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

trait Timestamped
{
    #[BidirektField]
    public string $createdAt;
}

abstract class Entity
{
    #[BidirektField]
    public string $id;
}

#[BidirektSchema]
class Pet extends Entity
{
    use Timestamped;

    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class Dog extends Pet
{
    #[BidirektField(description: 'The kennel club breed')]
    public string $name;

    public function __construct(
        #[BidirektField] public string $breed,
    ) {}
}
```

`src/Pet.php`
```php
<?php

namespace App;

trait Timestamped
{
    /** @bidirekt field */
    public string $createdAt;
}

abstract class Entity
{
    /** @bidirekt field */
    public string $id;
}

/** @bidirekt schema */
class Pet extends Entity
{
    use Timestamped;

    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class Dog extends Pet
{
    /** @bidirekt field description="The kennel club breed" */
    public string $name;

    public function __construct(
        /** @bidirekt field */
        public string $breed,
    ) {}
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  Dog:
    type: object
    properties:
      id:
        type: string
      createdAt:
        type: string
      name:
        type: string
        description: The kennel club breed
      breed:
        type: string
  Pet:
    type: object
    properties:
      id:
        type: string
      createdAt:
        type: string
      name:
        type: string
```

A parent or trait from a package is read from `vendor/`, found through Composer's autoload files, so it needs `composer install` first:

`composer.json`
```json
{
    "require": {
        "acme/model": "^1.0"
    }
}
```

`src/Pet.php`
```php
<?php

namespace App;

use Acme\Model\Entity;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet extends Entity
{
    #[BidirektField]
    public string $name;
}
```

```text
src/Pet.php:10 cannot find class "Acme\Model\Entity" in the project, and the vendor folder is not installed: run composer install
```

### Unions

A field typed by a union of schema classes declares the key of each member in `union`, also when the union is the items of a list or the values of a map in the PHPDoc. A tag names each member by its name in the contract. `null` in the type still makes the field, its items or its values `nullable`:

`src/Order.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class CardPayment
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema]
final class PixPayment
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public CardPayment|PixPayment $payment;

    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public CardPayment|PixPayment|null $refund;

    /** @var list<CardPayment|PixPayment> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public array $history;

    /** @var array<string, CardPayment|PixPayment|null> */
    #[BidirektField(union: ['card' => CardPayment::class, 'pix' => PixPayment::class])]
    public array $byMethod;
}
```

`src/Order.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class CardPayment
{
    /** @bidirekt field */
    public string $last4;
}

/** @bidirekt schema */
final class PixPayment
{
    /** @bidirekt field */
    public string $key;
}

/** @bidirekt schema */
final class Order
{
    /** @bidirekt field union={card: CardPayment, pix: PixPayment} */
    public CardPayment|PixPayment $payment;

    /** @bidirekt field union={card: CardPayment, pix: PixPayment} */
    public CardPayment|PixPayment|null $refund;

    /**
     * @bidirekt field union={card:CardPayment,pix:PixPayment}
     * @var list<CardPayment|PixPayment>
     */
    public array $history;

    /**
     * @bidirekt field union={card: CardPayment, pix: PixPayment}
     * @var array<string, CardPayment|PixPayment|null>
     */
    public array $byMethod;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  CardPayment:
    type: object
    properties:
      last4:
        type: string
  Order:
    type: object
    properties:
      payment:
        type: union
        members:
          card:
            ref: CardPayment
          pix:
            ref: PixPayment
      refund:
        type: union
        members:
          card:
            ref: CardPayment
          pix:
            ref: PixPayment
        nullable: true
      history:
        type: array
        items:
          type: union
          members:
            card:
              ref: CardPayment
            pix:
              ref: PixPayment
      byMethod:
        type: map
        values:
          type: union
          members:
            card:
              ref: CardPayment
            pix:
              ref: PixPayment
          nullable: true
  PixPayment:
    type: object
    properties:
      key:
        type: string
```

Spaces inside `union={...}` are optional. Without `union`, a union of schemas stops `generate`:

`src/Order.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class CardPayment
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema]
final class PixPayment
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField]
    public CardPayment|PixPayment $payment;
}
```

```text
src/Order.php:26 cannot translate union of classes "CardPayment|PixPayment": declare each member's key with #[BidirektField(union: …)]
```

The keys match the type: each schema of the union has one key, and each key names a schema of the union:

`src/Order.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class CardPayment
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema]
final class PixPayment
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class BoletoPayment
{
    #[BidirektField]
    public string $code;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField(union: ['card' => CardPayment::class, 'boleto' => BoletoPayment::class])]
    public CardPayment|PixPayment $payment;

    #[BidirektField(union: ['card' => CardPayment::class])]
    public CardPayment|PixPayment $refund;
}
```

```text
src/Order.php:33 the "union" of #[BidirektField] lists "BoletoPayment", which is not in "CardPayment | PixPayment": remove its key or add it to the type
src/Order.php:36 the "union" of #[BidirektField] misses "PixPayment" of "CardPayment | PixPayment": add a key for it
```

With `type`, a union has no PHP type to check against: `union` in `items` or `values` declares it, and a member is a class or a schema name:

`src/Order.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class CardPayment
{
    #[BidirektField]
    public string $last4;
}

#[BidirektSchema(name: 'Pix')]
final class PixPayment
{
    #[BidirektField]
    public string $key;
}

#[BidirektSchema]
final class Order
{
    #[BidirektField(type: 'array', items: ['union' => ['card' => CardPayment::class, 'pix' => 'Pix']])]
    public array $payments;
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
schemas:
  CardPayment:
    type: object
    properties:
      last4:
        type: string
  Order:
    type: object
    properties:
      payments:
        type: array
        items:
          type: union
          members:
            card:
              ref: CardPayment
            pix:
              ref: Pix
  Pix:
    type: object
    properties:
      key:
        type: string
```

### Handlers

A handler declares one resource: its method, in uppercase (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`), and its path as the contract writes it, with a `*` for each dynamic segment (`/pets/*` for `/pets/{id}`). `generate` never reads the routes of a framework: the method and path are the ones you write. The request and each response status take a body:

| Body                     | Attribute                                         | Tag                                   | Contract        |
| ------------------------ | ------------------------------------------------- | ------------------------------------- | --------------- |
| a schema class           | `Pet::class`                                      | `Pet`                                 | `Pet`           |
| a schema by its name     | `'Health'`                                        | `Health`                              | `Health`        |
| a list                   | `[Pet::class]`                                    | `Pet[]`                               | `PetList`       |
| a list with its own name | `['list' => Pet::class, 'name' => 'PetCollection']` | `Pet[] as PetCollection`              | `PetCollection` |
| no body                  | `null`                                            | nothing after the status or `request` | `~`             |

Without a request (no `request` argument, no `@bidirekt request` tag), the resource has no `request` key. A schema name, in an attribute or a tag, is the name in the contract, which is not always the class's name. A list is written once however many handlers use it.

In a tag, write a `*` before another segment as `*\/` (`/pets/*\/photos`): a bare `*/` would end the docblock. `generate` reads `*\/` as `*/`:

`src/photos.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Photo
{
    #[BidirektField]
    public string $url;
}

/**
 * @bidirekt provides GET /pets/*\/photos
 * @bidirekt response 200 Photo[]
 */
function listPhotos(): array
{
    return [];
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets/*/photos:
      get:
        responses:
          200: PhotoList
schemas:
  Photo:
    type: object
    properties:
      url:
        type: string
  PhotoList:
    type: array
    items:
      ref: Photo
```

#### Providers

`#[BidirektProvider]` and `@bidirekt provides <METHOD> <path>` go on a method, a function, a closure or an arrow function; a tag also goes above a route call, like `$app->get(...)` or `Route::get(...)`. Written without names, the arguments of `#[BidirektProvider]` are `method`, `path`, `responses`, `request`:

`src/PetsController.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema]
final class NewPet
{
    #[BidirektField]
    public string $name;
}

final class PetsController
{
    #[BidirektProvider('GET', '/pets', [200 => [Pet::class]])]
    public function list(): array
    {
        return [];
    }

    #[BidirektProvider(
        method: 'GET',
        path: '/pets/featured',
        responses: [200 => ['list' => Pet::class, 'name' => 'PetCollection']],
    )]
    public function featured(): array
    {
        return [];
    }

    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => Pet::class, 404 => null])]
    public function find(int $id): Pet
    {
        return new Pet();
    }

    #[BidirektProvider(
        method: 'POST',
        path: '/pets',
        request: NewPet::class,
        responses: [201 => Pet::class, 400 => null],
    )]
    public function create(NewPet $body): Pet
    {
        return new Pet();
    }

    #[BidirektProvider(method: 'PUT', path: '/pets/*', request: null, responses: [204 => null])]
    public function touch(int $id): void {}
}

$remove = #[BidirektProvider(method: 'DELETE', path: '/pets/*', responses: [204 => null])] fn (int $id) => null;
```

`src/PetsController.php`
```php
<?php

namespace App;

use Illuminate\Support\Facades\Route;

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field */
    public string $name;
}

/** @bidirekt schema */
final class NewPet
{
    /** @bidirekt field */
    public string $name;
}

final class PetsController
{
    /**
     * @bidirekt provides GET /pets
     * @bidirekt response 200 Pet[]
     */
    public function list(): array
    {
        return [];
    }

    /**
     * @bidirekt provides GET /pets/featured
     * @bidirekt response 200 Pet[] as PetCollection
     */
    public function featured(): array
    {
        return [];
    }

    /**
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200 Pet
     * @bidirekt response 404
     */
    public function find(int $id): Pet
    {
        return new Pet();
    }

    /**
     * @bidirekt provides POST /pets
     * @bidirekt request NewPet
     * @bidirekt response 201 Pet
     * @bidirekt response 400
     */
    public function create(NewPet $body): Pet
    {
        return new Pet();
    }

    /**
     * @bidirekt provides PUT /pets/*
     * @bidirekt request
     * @bidirekt response 204
     */
    public function touch(int $id): void {}
}

/**
 * @bidirekt provides DELETE /pets/*
 * @bidirekt response 204
 */
Route::delete('/pets/{id}', fn (int $id) => null);
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets:
      get:
        responses:
          200: PetList
      post:
        request: NewPet
        responses:
          201: Pet
          400: ~
    /pets/*:
      delete:
        responses:
          204: ~
      get:
        responses:
          200: Pet
          404: ~
      put:
        request: ~
        responses:
          204: ~
    /pets/featured:
      get:
        responses:
          200: PetCollection
schemas:
  NewPet:
    type: object
    properties:
      name:
        type: string
  Pet:
    type: object
    properties:
      name:
        type: string
  PetCollection:
    type: array
    items:
      ref: Pet
  PetList:
    type: array
    items:
      ref: Pet
```

The same method and path provided by two handlers stops `generate`. A handler written in a trait or a parent class counts once, where it is written.

#### Consumers

`#[BidirektConsumer]` and `@bidirekt consumes <provider> <METHOD> <path>` also name the provider. Written without names, the arguments of `#[BidirektConsumer]` are `provider`, `method`, `path`, `responses`, `request`:

`src/PaymentsClient.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Charge
{
    #[BidirektField]
    public string $id;
}

#[BidirektSchema]
final class NewCharge
{
    #[BidirektField]
    public int $amount;
}

final class PaymentsClient
{
    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        request: NewCharge::class,
        responses: [201 => Charge::class, 402 => null],
    )]
    public function charge(NewCharge $charge): void {}

    #[BidirektConsumer('payments_api', 'GET', '/charges/*', [200 => Charge::class])]
    public function find(string $id): void {}
}
```

`src/PaymentsClient.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Charge
{
    /** @bidirekt field */
    public string $id;
}

/** @bidirekt schema */
final class NewCharge
{
    /** @bidirekt field */
    public int $amount;
}

final class PaymentsClient
{
    /**
     * @bidirekt consumes payments_api POST /charges
     * @bidirekt request NewCharge
     * @bidirekt response 201 Charge
     * @bidirekt response 402
     */
    public function charge(NewCharge $charge): void {}

    /**
     * @bidirekt consumes payments_api GET /charges/*
     * @bidirekt response 200 Charge
     */
    public function find(string $id): void {}
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
consumes:
  payments_api:
    rest:
      /charges:
        post:
          request: NewCharge
          responses:
            201: Charge
            402: ~
      /charges/*:
        get:
          responses:
            200: Charge
schemas:
  Charge:
    type: object
    properties:
      id:
        type: string
  NewCharge:
    type: object
    properties:
      amount:
        type: integer
```

The same resource consumed by several handlers, with attributes, tags or both, is written once with the statuses of all of them. They declare the same request, and the same schema for a status they share; otherwise `generate` stops, pointing at each handler:

`src/CheckoutClient.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektConsumer;
use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Charge
{
    #[BidirektField]
    public string $id;
}

final class CheckoutClient
{
    #[BidirektConsumer(
        provider: 'payments_api',
        method: 'POST',
        path: '/charges',
        request: null,
        responses: [201 => Charge::class, 402 => null],
    )]
    public function charge(): void {}
}
```

`src/retry.php`
```php
<?php

namespace App;

/**
 * @bidirekt consumes payments_api POST /charges
 * @bidirekt request
 * @bidirekt response 201 Charge
 * @bidirekt response 409
 */
function retryCharge(): void {}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
consumes:
  payments_api:
    rest:
      /charges:
        post:
          request: ~
          responses:
            201: Charge
            402: ~
            409: ~
schemas:
  Charge:
    type: object
    properties:
      id:
        type: string
```

#### Arguments and statuses

An attribute's arguments are read from the code, never run: `generate` takes literals, arrays, `X::class`, class constants, also from a class or interface in `vendor/`, and `Enum::Case->value`, which PHP accepts in an attribute from 8.2. A status is an integer or a class constant that holds one, like `StatusCodeInterface::STATUS_OK`:

`src/PetsController.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektProvider;
use Bidirekt\PhpSdk\BidirektSchema;

enum Labels: string
{
    case Nickname = 'nick_name';
}

#[BidirektSchema(name: self::SCHEMA)]
final class Pet
{
    public const SCHEMA = 'PetCard';

    #[BidirektField(name: Labels::Nickname->value, description: self::SHOWN)]
    public string $nickname;

    private const SHOWN = 'Shown on the card';
}

final class PetsController
{
    public const STATUS_OK = 200;
    private const STATUS_GONE = 410;

    #[BidirektProvider(
        method: 'GET',
        path: '/pets/*',
        responses: [self::STATUS_OK => Pet::class, 404 => null, self::STATUS_GONE => null],
    )]
    public function find(int $id): Pet
    {
        return new Pet();
    }
}
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets/*:
      get:
        responses:
          200: PetCard
          404: ~
          410: ~
schemas:
  PetCard:
    type: object
    properties:
      nick_name:
        type: string
        description: Shown on the card
```

Anything else, like a concatenation or a global constant, stops `generate`:

`src/Pet.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

const BREED = 'breed';

#[BidirektSchema]
final class Pet
{
    #[BidirektField(description: 'Shown ' . 'on the card')]
    public string $nickname;

    #[BidirektField(name: BREED)]
    public string $breed;
}
```

```text
src/Pet.php:13 "'Shown ' . 'on the card'" cannot be read without running the code: write a literal, an array, X::class, a class constant or Enum::Case->value
src/Pet.php:16 "BREED" cannot be read without running the code: write a literal, an array, X::class, a class constant or Enum::Case->value
```

#### Mistakes `generate` stops at

A method in lowercase, a status with a leading zero, a status written twice (`200` and `'200'`, which PHP keeps as one), and an attribute and a tag on the same handler, schema or field:

`src/PetsController.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektProvider;

final class PetsController
{
    /**
     * @bidirekt provides get /pets
     * @bidirekt response 200
     */
    public function list(): void {}

    /**
     * @bidirekt provides POST /pets
     * @bidirekt response 0201
     */
    public function create(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/featured', responses: [200 => null, '200' => null])]
    public function featured(): void {}

    /**
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200
     */
    #[BidirektProvider(method: 'GET', path: '/pets/*', responses: [200 => null])]
    public function find(): void {}
}
```

```text
src/PetsController.php:10 method "get" must be uppercase: write "GET"
src/PetsController.php:17 response status "0201" must be written as 201
src/PetsController.php:21 "200" is declared more than once in "responses": keep one
src/PetsController.php:25 #[BidirektProvider] and "@bidirekt provides GET /pets/*" are both on this handler: keep one
src/PetsController.php:28 #[BidirektProvider] and "@bidirekt provides GET /pets/*" are both on this handler: keep one
```

An attribute argument with a misspelled name, or one a handler needs and lacks, stops `generate` too:

`src/PetsController.php`
```php
<?php

namespace App;

use Bidirekt\PhpSdk\BidirektProvider;

final class PetsController
{
    #[BidirektProvider(method: 'GET', path: '/pets')]
    public function list(): void {}

    #[BidirektProvider(method: 'GET', path: '/pets/*', response: [200 => null])]
    public function find(): void {}
}
```

```text
src/PetsController.php:9 #[BidirektProvider] must declare "responses"
src/PetsController.php:12 unknown argument "response" in #[BidirektProvider]: write method, path, responses or request
```

### Tags

A tag is `@bidirekt <keyword>` followed by its arguments, separated by spaces, at the start of a line of a `/** */` docblock; the keywords are `provides`, `consumes`, `request`, `response`, `schema` and `field`. A docblock declares at most one handler, and can also declare schemas, see [Schemas without a class](#schemas-without-a-class). `/* */`, `//` and `#` comments are not read, other tags in the same docblock (`@param`, `@return`, `@var`, `@OA\Get`) are left alone, and a `@bidirekt` later in a line, in a ` ``` ` fence or in an `@example` is not a tag. `generate` warns when one followed by a keyword is left unread, so it never vanishes silently:

`src/PetsController.php`
````php
<?php

namespace App;

final class PetsController
{
    /*
     * @bidirekt provides GET /pets/*
     * @bidirekt response 200
     */
    public function find(): void {}

    // @bidirekt provides GET /pets
    public function list(): void {}

    /**
     * Creates a pet, declared for bidirekt/php-sdk and read by @bidirekt tooling.
     * It is listed by @bidirekt provides GET /pets.
     *
     * ```php
     * // @bidirekt provides POST /fenced
     * ```
     *
     * @param array $body the new pet
     * @return void
     * @bidirekt provides POST /pets
     * @bidirekt response 201
     *
     * @example
     * @bidirekt provides POST /example
     */
    public function create(array $body): void {}
}
````

```text
src/PetsController.php:18 warning: "@bidirekt provides" is not at the start of a comment line, so it is not read: start the line with it
src/PetsController.php:30 warning: "@bidirekt provides" is inside an @example, so it is not read: move it above the @example
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets:
      post:
        responses:
          201: ~
```

Only a `description` value is quoted, and only a `union=` value is grouped in `{...}`; quotes or braces anywhere else stop `generate`:

`src/Pet.php`
```php
<?php

namespace App;

/** @bidirekt schema */
final class Pet
{
    /** @bidirekt field description="The pet's name, as the owner says it" */
    public string $name;

    /** @bidirekt field name="nick_name" */
    public string $nickname;
}

/**
 * @bidirekt provides GET /owners/{ id }
 * @bidirekt response 200
 */
function findOwner(): void {}
```

```text
src/Pet.php:11 cannot read "@bidirekt field name="nick_name"": remove the quotes, only description="<text>" takes them
src/Pet.php:16 cannot read "@bidirekt provides GET /owners/{ id }": write "@bidirekt provides <METHOD> <path>"
```

A misspelled tag name, like `@Bidirekt` or `@bidirektresponse`, is not read, and `generate` warns:

`src/PetsController.php`
```php
<?php

namespace App;

final class PetsController
{
    /**
     * @bidirekt provides GET /pets
     * @bidirekt response 200
     * @Bidirekt response 404
     */
    public function list(): void {}
}
```

```text
src/PetsController.php:10 warning: "@Bidirekt" is not a @bidirekt tag: write "@bidirekt <keyword>"
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets:
      get:
        responses:
          200: ~
```

Tags of a handler, a schema or a field written in two docblocks on the same element stop `generate`:

`src/PetsController.php`
```php
<?php

namespace App;

/** @bidirekt schema */
/** @bidirekt schema name=Animal */
final class Pet
{
    /** @bidirekt field name=pet_name */
    /** @bidirekt field */
    public string $name;
}

final class PetsController
{
    /** @bidirekt provides GET /pets */
    /** @bidirekt provides GET /animals */
    public function list(): void {}
}
```

```text
src/PetsController.php:6 "@bidirekt schema" is declared more than once on class "Pet": keep one
src/PetsController.php:10 "@bidirekt field" is declared more than once on property "name": keep one
src/PetsController.php:17 a handler is declared more than once on this element: keep one "@bidirekt provides" or "@bidirekt consumes" comment
```

A docblock goes above the element it annotates, and above its attributes, which is where PHP attaches it. A tag docblock `generate` can't attach to an element, like one between an attribute and its method or at the end of a file, is not read, and `generate` warns:

`src/PetsController.php`
```php
<?php

namespace App;

use Attribute;

#[Attribute]
final class Route
{
    public function __construct(public string $path) {}
}

final class PetsController
{
    /**
     * @bidirekt provides GET /pets
     * @bidirekt response 200
     */
    #[Route('/pets')]
    public function list(): void {}

    #[Route('/pets/{id}')]
    /** @bidirekt provides GET /pets/* */
    public function find(): void {}
}

/** @bidirekt provides DELETE /pets/* */
```

```text
src/PetsController.php:23 warning: this comment is not above a statement or declaration, so its "@bidirekt" tags are not read: move it above the statement or declaration, before its attributes
src/PetsController.php:27 warning: this comment is not above a statement or declaration, so its "@bidirekt" tags are not read: move it above the statement or declaration, before its attributes
```

```yaml
# Generated by bidirekt/php-sdk. Do not edit.
provides:
  rest:
    /pets:
      get:
        responses:
          200: ~
```

Other tools that read docblocks need to leave `@bidirekt` alone:

- `doctrine/annotations` (Symfony annotation routing, Doctrine ORM 2, serializer or validator annotations) stops at `@bidirekt` as an annotation that "was never imported". Ignore it once in your app's bootstrap with `\Doctrine\Common\Annotations\AnnotationReader::addGlobalIgnoredName('bidirekt');`, or per class with `@IgnoreAnnotation("bidirekt")` in the class docblock.
- PHP-CS-Fixer's `phpdoc_to_comment` rule, in the `@Symfony` and `@PhpCsFixer` sets and in Pint's `symfony` preset, turns a `/** */` above a statement, like `$app->get(...)`, into a `/* */`, which `generate` does not read. Keep it with `'phpdoc_to_comment' => ['ignored_tags' => ['bidirekt']]` in `.php-cs-fixer.php`, or the same rule config in `pint.json`.

## The `generate` command

```sh
vendor/bin/php-sdk generate
vendor/bin/php-sdk generate --check
vendor/bin/php-sdk generate --project apps/api --out apps/api/contracts/generated.yaml
```

`generate` reads the project in the current folder and writes `contracts/generated.yaml` in it. With a `composer.json`, it reads every `.php` file of the folder except `vendor/` (or the `config.vendor-dir`), `node_modules/`, hidden folders and the paths of `autoload-dev`; without one, every `.php` file except the same folders. It reads the source code and never runs your app. It reads `vendor/` only to find a parent, a trait, an enum or a constant your annotated code uses, through Composer's class maps (`vendor/composer/autoload_psr4.php`, `autoload_namespaces.php` and `autoload_classmap.php`), from the `composer.json` closest to the project, going up as Composer does in a monorepo, but never to your home folder or above it. It loads those maps with `require`, since they only return arrays, and never loads `vendor/autoload.php` nor the `autoload.files`, which would run code. A package installed from a Composer `path` repository is read as your code. A file that never mentions `bidirekt` is parsed only when your annotated code uses a class it declares, as a parent, a trait, a type or a constant. A syntax error stops it in a file with annotations, or in a file it parses for that reason; elsewhere it is left to PHP.

- `--project <folder>` reads another project, and writes to its folder.
- `--out <file>` writes to another file.
- `--check` writes nothing and exits with 1 when the file on disk differs from what `generate` would write (`\r\n` counts as `\n`), or doesn't exist.

The file starts with `# Generated by bidirekt/php-sdk. Do not edit.` and has the `provides`, `consumes` and `schemas` sections that have content, in a fixed order: resources by path, method and status, schemas in alphabetical order, fields and members in the order of the code.

Each error goes to stderr as `file:line message`, with the file relative to the current folder, sorted by file and line; on an error nothing is written and the exit code is 1. A warning is printed as `file:line warning: message` in the same list and doesn't change the exit code. A project with no annotation writes nothing and exits with 1.

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
