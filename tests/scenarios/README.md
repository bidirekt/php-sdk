# Scenarios

Each folder is a project as a developer has it, run end to end by `run.sh`: `composer install`, `vendor/bin/php-sdk generate`, `generate --check`, then `bidirekt validate` and `bidirekt publish` of the generated contract on a broker 0.2.0. What the scenario checks is what the developer sees, so the SDK may change in any way inside as long as every scenario passes. Adding a scenario is adding a folder.

A flow scenario is two teams living with each other's contracts: several projects, a provider and its consumers, and the commands their developers type, in order, with what each one prints, from `generate` to the `can-i-deploy` that fails when the provider breaks a field a consumer reads.

Run them all, or some by name, with only Docker installed:

```sh
sh tests/scenarios/run.sh
sh tests/scenarios/run.sh slim
```

## A scenario

- `.php-version`: the PHP version it runs on, like `8.0`. `compose.yaml` has a `php-<version>` service for each version, an official `php:<version>-cli-alpine` image with Composer; a new version needs a new service there.
- The project: `composer.json` and its committed `composer.lock`, with the framework from Packagist and the SDK from the checkout. Copy the `repositories` entry and the `require-dev` of `slim/composer.json`: a `path` repository at `../../..` that copies the SDK (`symlink: false`), with version `dev-main` and no reference, so the lock stays the same from commit to commit.
- `args.txt` (optional): the flags after `generate`, separated by whitespace, like `--project apps/api`. The contract is read from `contracts/generated.yaml` of the `--project` folder, or of the scenario's.
- What the developer must see:
  - `expected.yaml`: `generate` exits 0 and writes only the contract, equal to this file byte by byte.
  - No `expected.yaml`: `generate` exits 1 and writes nothing.
  - `expected-stderr.txt`: the stderr of `generate`, byte by byte, with or without `expected.yaml` (warnings, or the errors); without the file, stderr is empty. stdout is always empty.

Name the folder in kebab-case: its participant on the broker is the name in snake_case.

## A flow scenario

- A folder per service, each a project as above, with its `.php-version`, `composer.json` and `composer.lock`; its `path` repository is at `../../../..`, a folder deeper. `run.sh` runs `composer install` in each one before the steps.
- `steps.txt`: the terminal of the developers, as they would see it. A line `<service>$ <command>` is a command typed in the folder of that service; the lines below it, up to the next command, are what it prints, stdout and stderr together; `[<code>]` closes the output of a command that exits with a code other than 0. A command that starts with `bidirekt` runs in the CLI 0.2.0, with the broker of the run; any other runs with `sh` in the service's PHP, where `vendor/bin/php-sdk`, `cat` and `patch` are. Nothing else in the file: no comments, no blank lines but the ones a command prints.
- Anything else the steps use, like a patch for `patch -p1 < ../<name>.patch`, beside `steps.txt`.

`cross-service` is the example: `petstore-api` (Slim 4 on PHP 8.5, attributes) publishes v1 and records its deployment to `production`; `storefront` (Guzzle on PHP 8.0, an attribute and a tag on its client) validates, publishes, gets a green `can-i-deploy` against the deployed `petstore-api`, and records its deployment; then `rename-pet-name.patch` renames the `name` that `storefront` reads, and the regenerated `petstore-api` gets a red `validate` and `can-i-deploy` with the break.

A flow of one service shows what the broker answers to a contract `generate` writes: `mistake-path-parameter` declares `/adoptions/{petId}`, which `generate` writes as is, and `validate` refuses.

To write the steps, write the commands alone and run the scenario: the diff shows what each one printed, to read and copy in.

The participants are the names the steps give them, and every scenario of a run shares the broker: give each flow participants no other scenario uses.

The project files are code of an example project, never run by the tests: PHPStan, PHP-CS-Fixer and PHPUnit skip this folder.

## What `run.sh` does

It starts the broker of `../e2e/compose.yaml`, creates the API key and the `production` environment, and then, per project scenario:

1. In the scenario's `php-<version>` service, copies the folder, without `vendor/`, to the same path over the checkout (so `../../..` is the SDK), and runs `composer install` there. The scenarios of a run share the Composer cache.
2. Runs `generate` with `args.txt` and compares exit code, stdout, stderr, the contract and the files written; with `expected.yaml`, runs `generate --check`, which must exit 0 with the same stderr.
3. With `expected.yaml`: `bidirekt create-participant`, `bidirekt validate --environment production` and `bidirekt publish --version 1` of the generated contract.

Then, per flow scenario (run without names, the flows come after the project scenarios), it copies the folder in the same way, runs `composer install` in each service, runs each command of `steps.txt` in the copy of its service, and compares what they printed with `steps.txt`.

It prints `ok <scenario>` or `FAIL <scenario>` with the reason (the diff when the contract, stderr or transcript differs), then a summary line, and exits 1 when a scenario fails. It prints the broker's log only when a broker step failed or a flow's transcript differs. On exit and on a signal it removes the compose project with its volumes.

Nothing is deployed in `production` for a project scenario, so `validate` refuses a contract that consumes: "not deployed". A service that consumes belongs in a flow scenario, after the steps that publish its provider and record its deployment.

## Writing the lock

From the repository root, on the scenario's PHP, without installing anything in the folder:

```sh
docker compose -p php-sdk-scenarios-lock -f tests/scenarios/compose.yaml run --rm -T \
  -v "$PWD/tests/scenarios/slim:/sdk/tests/scenarios/slim" -w /sdk/tests/scenarios/slim \
  php-8.0 composer update --no-install </dev/null
docker compose -p php-sdk-scenarios-lock -f tests/scenarios/compose.yaml --profile '*' down -v --rmi local
```

For a service of a flow scenario, mount and work in `tests/scenarios/<flow>/<service>` instead.
