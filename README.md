# Jam Engine

Reusable [HydePHP](https://hydephp.com) engine for the websites of the Hagenberg jams. Each jam has its own site repository with its content, configuration and theme, and installs this package through Composer.

**Status:** Early development (`0.x`). The code was extracted from the Hagenberg Game Jam website and still contains game-specific behaviour, which is being replaced by configuration.

## Install

```json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/Hagenberg-Jams/jam-engine" }],
    "require": { "hagenberg-jams/jam-engine": "dev-main" }
}
```

The service provider is discovered automatically. It registers the views, build tasks and console commands.

## Overriding views

A site can override any engine view by creating a file with the same path in its own `resources/views/`.

## Styles and scripts

Import the engine's assets in the site's `resources/assets/app.css`, and tell Tailwind where the site's templates are:

```css
@import '../../vendor/hagenberg-jams/jam-engine/resources/assets/app.css';
@source '../../_pages';
@source '../views';
```

Do the same for `resources/assets/app.js`.

## Development

```bash
composer install
composer test   # Pint and PHPStan
```
