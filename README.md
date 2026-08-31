# Elavira

Elavira is a WordPress accessibility auditing and guidance plugin. The current
implementation contains the Phase 1 application foundation only.

## Development

Install development dependencies and run the complete foundation check:

```sh
composer install
composer check
```

Elavira has no production Composer package dependencies. Release artifacts must
still include Composer's generated `vendor/autoload.php`; WordPress site owners
must not be required to run Composer after installing the plugin.

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for the module layout and
current architectural boundaries.
