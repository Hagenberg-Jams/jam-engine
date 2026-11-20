# Jam Engine

Reusable [HydePHP](https://hydephp.com) engine for the websites of the Hagenberg jams. Each jam has its own site repository with its content, configuration and theme, and installs this package through Composer.

**Status:** Early development (`0.x`). The code was extracted from the Hagenberg Game Jam website.

## Install

```json
{
    "repositories": [{ "type": "vcs", "url": "https://github.com/Hagenberg-Jams/jam-engine" }],
    "require": { "hagenberg-jams/jam-engine": "^0.2" }
}
```

The service provider is discovered automatically. It registers the views, build tasks and console commands, and adds the pages generated from `_data/` (home, imprint, 404, rules, years, projects, people, registration) to Hyde's page discovery, so `php hyde serve` and `php hyde build` both have them. A site replaces one of the static pages (`index`, `imprint`, `404`, `rules`) with a page of the same name in its `_pages/`.

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

Store the topic and what belongs to it as GitHub secrets:

| Secret | Content | YAML field after the jam |
| --- | --- | --- |
| `JAM_TOPIC` | The topic itself | `topic` |
| `JAM_TOPIC_VIDEO` | Optional YouTube ID of a reveal video, not the URL | `topic_video` |
| `JAM_TOPIC_DESCRIPTION` | Optional plain text that explains the topic. Line breaks are kept, Markdown and HTML are not rendered. | `topic_description` |

The next deployment from `main` transfers them to Cloudflare as one secret, `JAM_TOPIC_DATA`, together with the year of the jam. The function ignores a topic for any other year, so a secret left over from an earlier jam never needs to be deleted in Cloudflare. The page shows a countdown, and a Pages Function hands out the topic, video and description from the release time on. If this year's jam has no video or description, delete `JAM_TOPIC_VIDEO` or `JAM_TOPIC_DESCRIPTION` on GitHub, otherwise last year's value is shown with the new topic. After the jam, add the values to the YAML file. The year page shows the topic and the description:

```yaml
topic: 'Hidden Worlds'
topic_description: |
  Everything has a side that nobody sees.

  Show it.
```

To set, change or remove the time later, use the command. It only changes the `topic_release` line:

```bash
php hyde jam:topic-release 2026-11-20 19:00        # newest jam
php hyde jam:topic-release 2026-11-20 19:00 --year=2026
php hyde jam:topic-release --remove
```

The build fails if `topic:`, `topic_video:` or `topic_description:` is in the YAML file before the release time.

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
        'programs' => ['Media Technology and Design (MTD.ba)', '...'],   // each person picks one
        'programs_label' => 'Study programme',
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
- **Seats:** Each person can tick that they take part remotely only. Seats on site go out in the order of registration and count only the people on site. A team that does not fit goes to the waiting list or takes part remotely, as it chose in the form. Organisers can move teams in the admin page.
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

## Upload page

With `submission.enabled`, a site gets a page at `/submit`, where teams upload their project at the end of the jam. Teams choose their team, enter the upload code from the announcement and upload:

- **Games:** one ZIP file for each platform. **Animations:** one video file. The preset sets which.
- **README.md:** the page offers a template with the team filled in. The function checks that the title is replaced and every required section has text. HTML comments in the template are hints and do not count.
- **Screenshots:** a number of images, checked for their size in the browser.

README and screenshots are needed with the first upload of a team. Later uploads, for example a bugfix, may leave them out. The page suggests the next version (1.0.0, then 1.0.1, …) and builds the file name, for example `TheAwesomes_MostAwesomeGame_Win_1.0.0.zip`. A version must be higher than the last one of the same platform, so nothing is ever overwritten. Uploads after the deadline are marked as late, and organisers get a mail for each upload.

```php
'submission' => [
    'enabled' => true,
    'deadline' => '',                    // empty: the end of the latest jam; later uploads are marked as late
    'closes' => '2027-01-31 23:59',      // empty: open while enabled is true
    'max_size_mb' => 4096,
    'screenshots' => ['min' => 4, 'max' => 8, 'width' => 1920, 'height' => 1080],
    'texts' => ['intro' => ''],
    // turnstile_site_key, notify and from: empty uses the settings of the registration form.
],
```

- **Teams:** `_data/teams/YYYY.yaml` with a list of team names, for example `- The Awesomes`. Without the file, the page offers the confirmed registrations of the registration form.
- **Storage:** the files go to a private R2 bucket `<project>-submissions` in the EU, `<project>-submissions-preview` for previews. Each team has a folder `YYYY/<team>/` with `uploads/`, `README.md`, every earlier README in `readme/`, and the screenshots in `screenshots/`. Large files are uploaded in parts of 50 MB, so there is no limit from the functions.

### Cloudflare setup

1. Give the deploy token `Workers R2 Storage: Edit`. Without it, create the two buckets by hand in the EU jurisdiction.
2. Create a Turnstile widget for the domain and the `pages.dev` host, if the registration form does not have one yet. Put the site key in the config and the secret key in the GitHub secret `TURNSTILE_SECRET`.
3. Store the upload code as the GitHub secret `JAM_SUBMISSION_CODE`, and announce it to the teams, for example on Discord. It is compared without case.

The workflow then creates the buckets and binds them to the Pages project as `JAM_SUBMISSIONS`. To try the page locally, build the site and run `npx wrangler pages dev _site --r2 JAM_SUBMISSIONS --binding JAM_SUBMISSION_CODE=test --binding TURNSTILE_SECRET=1x0000000000000000000000000000000AA` with the Turnstile test key `1x00000000000000000000AA` in the config.

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
    uses: Hagenberg-Jams/jam-engine/.github/workflows/site.yml@v0.2.0
    with:
      project-name: my-jam
      site-url: https://example.org
    secrets: inherit
```

The daily run removes past events from the home page and writes a released topic into the HTML.

The workflow is pinned to the same release as the package, so a change to the build or the deployment only reaches a site through a pull request in that site.

## Releases and updates

1. Build a feature on a branch of this repository. New features are off by default and switched on in the `config/jam.php` of a site.
2. To try it on a site before the release, open a branch in the site repository that points to the engine branch, and open a pull request there. Its preview shows the feature:

   ```json
   "hagenberg-jams/jam-engine": "dev-my-feature"
   ```

   ```yaml
   uses: Hagenberg-Jams/jam-engine/.github/workflows/site.yml@my-feature
   ```

3. Merge the feature here and tag a release, for example `git tag -a v0.3.0 -m "..." && git push origin v0.3.0`. The release workflow runs the tests and creates the GitHub release.
4. Dependabot opens two pull requests in every site within a week: `composer` for the package and `actions` for the workflow. **Merge both**, so that package and workflow have the same version. Under **Insights → Dependency graph → Dependabot**, **Check for updates** starts it right away.
5. Check the preview of each pull request, then merge. To publish a feature on all sites at the same time, merge the pull requests of all sites one after the other.

Do not merge engine updates into a site from one week before its jam until the end of its voting, unless they fix a problem.

## Commands

`jam:create-jam`, `jam:topic-release`, `jam:add-project`, `jam:add-sponsor`, `jam:remove-sponsor`, `jam:validate`, `jam:check-links`, `jam:migrate-data`, `jam:print`, `jam:sync-r2`, `jam:update-checksums` and `jam:convert-images`.

## Development

```bash
composer install && npm install
composer test          # Pint, PHPStan and Pest
npm test               # Pages Function
npm run test:browser   # Playwright, needs: npx playwright install chromium
```
