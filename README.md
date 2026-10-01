# A flexible taxonomy and hierarchical term management plugin for Filament.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/eyawiin/filament-taxonomies.svg?style=flat-square)](https://packagist.org/packages/eyawiin/filament-taxonomies)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/eyawiin/filament-taxonomies/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/eyawiin/filament-taxonomies/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/eyawiin/filament-taxonomies/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/eyawiin/filament-taxonomies/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/eyawiin/filament-taxonomies.svg?style=flat-square)](https://packagist.org/packages/eyawiin/filament-taxonomies)



Filament Taxonomies lets administrators define taxonomies and organize their terms into nested trees. The package registers a Taxonomies resource in your Filament panel, where you can create taxonomies and manage their terms.

## Installation

You can install the package via composer:

```bash
composer require eyawiin/filament-taxonomies
```

> [!IMPORTANT]
> If you have not set up a custom theme and are using Filament Panels follow the instructions in the [Filament Docs](https://filamentphp.com/docs/4.x/styling/overview#creating-a-custom-theme) first.

After setting up a custom theme add the plugin's views to your theme css file or your app's css file if using the standalone packages.

```css
@source '../../../../vendor/eyawiin/filament-taxonomies/resources/**/*.blade.php';
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-taxonomies-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-taxonomies-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-taxonomies-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

Register the plugin on your Filament panel:

```php
use Eyawiin\FilamentTaxonomies\FilamentTaxonomiesPlugin;
use Filament\Panel;

public function panel(Panel $panel): Panel
{
    return $panel
        // Keep your existing panel configuration here.
        ->plugin(FilamentTaxonomiesPlugin::make());
}
```

Open **Taxonomies** in the panel navigation to create a taxonomy, then choose **Manage Terms** to organize its terms in a tree. Drag a term by its handle and drop it near the top of another row to place it before, in the middle to make it a child, or near the bottom to place it after. You can also use the row's **Move up** and **Move down** buttons to reorder siblings without dragging. Use **Edit** to change a term's parent.

## Testing

```bash
composer test
npm run test:js
```

Foundation contracts and executable pending regressions are documented in
[the contributor foundation guide](.github/FOUNDATION.md). Run `composer test:pending`
and `npm run test:js:pending` explicitly to reproduce unfinished milestone requirements;
these currently fail and are separate from required CI. Promote each regression into
the required suite with its implementation.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Eyawiin](https://github.com/Eyawiin)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
