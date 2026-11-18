# Fixtures

Each folder is one case of `generate`, run by `tests/FixturesTest.php`. Adding a case is adding a folder.

The runner copies the folder to a temporary directory and runs `generate` there, in the same process, with that directory as the cwd, so nothing is written into the repository.

A case holds:

- The annotated project: `.php` files with or without a `composer.json`. The SDK attributes resolve by name only, since the code is read and never run, so no install is needed. A case that needs a parent, trait, enum or constant from `vendor/` ships its own `vendor/`: the package files and the `vendor/composer/autoload_psr4.php`, `autoload_namespaces.php` or `autoload_classmap.php` that point at them, in the shape Composer generates (`$vendorDir . '/…'`, `$baseDir . '/…'`).
  The fixtures are code of example projects, with errors on purpose: PHPStan, PHP-CS-Fixer and PHPUnit skip this folder, and a case may use any PHP syntax.
- Exactly one expected result:
  - `expected.yaml`: `generate` exits 0 and `contracts/generated.yaml` equals this one byte by byte. stderr equals `expected-stderr.txt` when the case has one (warnings), and is empty otherwise.
    Every `expected.yaml` must be a contract broker 0.2.0 accepts: the `e2e` job publishes each one.
    A port of a `typescript-sdk` fixture keeps its folder name and its `expected.yaml` from line 2 on; line 1 is each SDK's header.
  - `expected-error.txt`: `generate` exits 1, stderr equals this file byte by byte, and no file in the case changes.
- `args.txt` (optional): the flags passed after `generate`, separated by whitespace, e.g. `--check`. The result is always read from `contracts/generated.yaml` of the case folder.

For `--check`, commit the `contracts/generated.yaml` the check reads inside the case: up to date pairs it with an identical `expected.yaml`, out of date with an `expected-error.txt`.
