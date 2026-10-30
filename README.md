# Jam Engine

Reusable [HydePHP](https://hydephp.com) engine for the websites of the Hagenberg jams. Each jam has its own site repository with its content, configuration and theme, and installs this package through Composer.

**Status:** Early development (`0.x`). The code was extracted from the Hagenberg Game Jam website.

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

## Configuration

A site's `config/jam.php` only sets what differs from the defaults (`config/jam.php`) and its preset (`config/presets/`):

```php
return [
    'type' => 'animation',
    'website' => 'https://example.org',
    'latest_jam' => '2026',
    'awards' => ['sound' => 'Best Sound'],
];
```

Content lives in `_data/`: `jams/YYYY.yaml`, `projects/YYYY.yaml`, `homepage.yaml`, `rules.yaml` and `imprint.yaml`.

Brand colours are set in the site's stylesheet by overriding `--jam-primary-50` to `--jam-primary-950` on `:root`.

## Topic release

Set only the time in the jam's YAML file, and never the topic. Quote the values, so that they keep their time zone:

```yaml
startdate: '2026-11-21T09:00:00+01:00'
enddate: '2026-11-22T21:00:00+01:00'
topic_release: '2026-11-20T18:00:00+01:00'
```

Store the topic as the GitHub secret `JAM_TOPIC`, and optionally a YouTube ID as `JAM_TOPIC_VIDEO`. The next deployment transfers them to Cloudflare. The page shows a countdown, and a Pages Function hands out the topic from the release time on. After the jam, add the topic to the YAML file as `topic:`.

The build fails if `topic:` is in the YAML file before the release time.

## Deployment

Sites use the reusable workflow with a short workflow file of their own:

```yaml
name: Site
on:
  push: { branches: [main] }
  pull_request:
  schedule: [{ cron: '0 4 * * *' }]
  workflow_dispatch:
jobs:
  site:
    permissions:
      contents: read
      pull-requests: write
    uses: Hagenberg-Jams/jam-engine/.github/workflows/site.yml@main
    with:
      project-name: my-jam
      site-url: https://example.org
    secrets: inherit
```

The daily run removes past events from the home page and writes a released topic into the HTML.

## Commands

`jam:create-jam`, `jam:add-project`, `jam:add-sponsor`, `jam:remove-sponsor`, `jam:validate`, `jam:check-links`, `jam:migrate-data`, `jam:print`, `jam:sync-r2`, `jam:update-checksums` and `jam:convert-images`.

## Development

```bash
composer install && npm install
composer test          # Pint, PHPStan and Pest
npm test               # Pages Function
npm run test:browser   # Playwright, needs: npx playwright install chromium
```
