# Bidirekt PHP SDK

[![CI](https://img.shields.io/github/actions/workflow/status/bidirekt/php-sdk/ci.yml?branch=main&label=CI)](https://github.com/bidirekt/php-sdk/actions/workflows/ci.yml) [![License](https://img.shields.io/github/license/bidirekt/php-sdk)](LICENSE)

In Bidirekt, each side of an integration declares in a contract file what it provides and what it consumes. This SDK writes that file from the code of a service on PHP 8.0 or later: you annotate the schemas and the handlers that provide or consume each resource, with attributes or with `@bidirekt` tags in docblocks, run `generate`, and get `contracts/generated.yaml`, ready for `bidirekt validate` and `bidirekt publish`.

## Development

Read [CONTRIBUTING.md](CONTRIBUTING.md). With PHP 8.0, the version CI uses, install the dependencies and run the checks from the repository root before you open a pull request:

```sh
composer install
composer check
composer lint
composer test
```

## License

MIT, see [LICENSE](LICENSE).
