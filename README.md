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

Store the topic as the GitHub secret `JAM_TOPIC`, and optionally a YouTube ID as `JAM_TOPIC_VIDEO`. The next deployment from `main` transfers both to Cloudflare as one secret, `JAM_TOPIC_DATA`, together with the year of the jam. The function ignores a topic for any other year, so a secret left over from an earlier jam never needs to be deleted in Cloudflare. The page shows a countdown, and a Pages Function hands out the topic from the release time on. After the jam, add the topic to the YAML file as `topic:`.

To set, change or remove the time later, use the command. It only changes the `topic_release` line:

```bash
php hyde jam:topic-release 2026-11-20 19:00        # newest jam
php hyde jam:topic-release 2026-11-20 19:00 --year=2026
php hyde jam:topic-release --remove
```

The build fails if `topic:` is in the YAML file before the release time.

## Registration form

With `registration.form.enabled`, a site gets a registration form at `/register`. Teams register, get a mail with a private link to change or cancel, and organisers manage the registrations at `/admin/registrations`.

```php
'registration' => [
    'active' => true,
    'url' => '',                       // empty: use the form
    'deadline' => '2026-12-07 23:59',
    'form' => [
        'enabled' => true,
        'team_size' => [1, 6],
        'onsite_capacity' => 100,      // null: no limit
        'fields' => ['participation' => true, 'discord' => true, 'programs' => true, 'comment' => true],
        'programs' => ['Media Technology and Design (MTD.ba)', '...'],
        'nickname_label' => 'Nickname',
        'notify' => ['orga@example.org'],
        'from' => 'noreply@example.org',
        'retention_days' => 90,
        'turnstile_site_key' => '0x...',
        'access' => ['team' => 'my-team', 'aud' => '...'],
        'texts' => ['intro' => '', 'confirmed' => '', 'declined' => ''],
    ],
],
```

- **Names:** Every person gives a real name and optionally a nickname, and chooses which of the two appears on the website. Real names, e-mail addresses and Discord names never leave the database except through the admin page.
- **Seats:** Seats on site go out in the order of registration. A team that does not fit goes to the waiting list or takes part remotely, depending on its wish. Organisers can move teams in the admin page.
- **Exports:** The admin page exports a CSV with real names, for example for the security staff, and `teams-YYYY.json` with team names and shown names only. Put that file in `_input/`, and `jam:add-project` offers the teams.
- **Deletion:** Every run of the site workflow deletes registrations whose jam ended more than `retention_days` ago.
- **Organisers:** `notify` lists addresses that get a mail for each new, changed or cancelled registration. To keep private addresses out of the public repository, put them comma-separated in the GitHub secret `JAM_REGISTRATION_NOTIFY` instead.
- **Privacy:** The imprint gets a section on the registration, linked from the form. Check it for your organisation.

### Cloudflare setup

1. Book **Workers Paid**, which the Email Service needs to mail any address.
2. Enable sending for the domain of `from` under **Email Service → Sending**.
3. Create a **Turnstile** widget for the domain and the `pages.dev` host of the project. Put the site key in the config and the secret key in the GitHub secret `TURNSTILE_SECRET`.
4. Create a **Cloudflare Access** self-hosted application for `/admin` and `/api/admin` on the domain, and `/admin` on `<project>.pages.dev` and `*.<project>.pages.dev`. Use One-time PIN and a policy with the organisers' addresses. Put the team name and the AUD tag in the config.
5. Give the deploy token `D1: Edit` in addition to `Cloudflare Pages: Edit`.
6. Create a second token with only `Email Sending: Edit` and store it as the GitHub secret `EMAIL_API_TOKEN`. It is handed to the function, so it should not be able to do anything else.

The workflow then creates one D1 database for production and one for previews, applies the migrations, and sets the bindings and secrets of the Pages project.

To run the functions locally, use a short storage path on Windows, for example `npx wrangler pages dev _site --d1 JAM_DB --persist-to C:/tmp/jam`. Long paths make the local database fail.

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

`jam:create-jam`, `jam:topic-release`, `jam:add-project`, `jam:add-sponsor`, `jam:remove-sponsor`, `jam:validate`, `jam:check-links`, `jam:migrate-data`, `jam:print`, `jam:sync-r2`, `jam:update-checksums` and `jam:convert-images`.

## Development

```bash
composer install && npm install
composer test          # Pint, PHPStan and Pest
npm test               # Pages Function
npm run test:browser   # Playwright, needs: npx playwright install chromium
```
