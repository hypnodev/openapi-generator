# openapi-generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/hypnodev/openapi-generator.svg?style=flat-square)](https://packagist.org/packages/hypnodev/openapi-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/hypnodev/openapi-generator.svg?style=flat-square)](https://packagist.org/packages/hypnodev/openapi-generator)

This package allows you to generate OpenAPI documentation from your Laravel application, without extends your controllers or create extra file! Do it from phpDoc or use Attributes.
The packages will bring directly routes from your application (default: /api), request and response from your controllers and models and will generate a full OpenAPI documentation.

## Installation

You can install the package via composer:

```bash
composer require hypnodev/openapi-generator
```
## Usage

```bash
$ php artisan openapi:generator api --output=storage/openapi.yaml
```

## Docs

Have a look to the [docs](https://openapi-generator.cristiancosenza.dev) for more information.

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email me@cristiancosenza.com instead of using the Issue page.

## Credits

-   [Cristian "hypnodev" Cosenza](https://github.com/hypnodev)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
