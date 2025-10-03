# bitexpert/phpstan-sylius

This package provides some additional features for PHPStan to detect configuration issues in Sylius projects.

## Requirements

PHP: PHP 8.2 or higher

Sylius: Sylius 2.0 or higher

PHPStan: PHPStan 2.0 or higher

## Installation

The preferred way of installing `bitexpert/phpstan-sylius` is through Composer.
You can add `bitexpert/phpstan-sylius` as a dev dependency, as follows:

```
composer.phar require --dev bitexpert/phpstan-sylius
```

### PHPStan configuration

If you have not already a PHPStan configuration file `phpstan.neon` in your project, create a new empty file next to your `composer.json` file.

See [here](https://phpstan.org/config-reference) what options PHPStan allows you to configure. 

## Feature overview

This PHPStan extension works for both Sylius plugins and Sylius application projects.

The following rules have been implemented:
- Rule to check if resource classes defined in AbstractGrid::getResourceClass() exist
- Rule to check that configured grid fields belong to the configured resource class
- Rule to check that configured filter fields belong to the configured resource class
- Rule to check that grid classes configured via the `Index` attribute exist

Current assumptions:
- Resource entities are configured via attributes
- Grids are configured by extending the `Sylius\Bundle\GridBundle\Grid\AbstractGrid` class
- Grid field and filter configuration is using the factory classes from the Grid Bundle

## Contribute

Please feel free to fork and extend existing or add new features and send a pull request with your changes! To establish
a consistent code quality, please provide unit tests for all your changes and adapt the documentation.

## Want To Contribute?

If you feel that you have something to share, then we’d love to have you.
Check out [the contributing guide](CONTRIBUTING.md) to find out how, as well as what we expect from you.

## License

PHPStan Sylius Extension is released under the MIT License.

