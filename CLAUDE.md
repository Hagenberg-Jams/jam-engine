# Jam Engine

Composer package with the shared HydePHP code for the Hagenberg jam websites: build tasks, commands, data access, views and frontend sources. See `README.md` for how sites consume it.

## Rules

- No jam-specific content in this repo: no jam names, URLs, sponsors or texts. These belong in the site repos.
- Express differences between jam types through configuration. Views must not check for a specific jam type.
- Public URLs of existing sites must stay stable: `/YYYY`, `/YYYY/<slug>`, `/people`, `/person/<slug>`, `/media/...` and `/games/YYYY/...`.
- A jam's topic must never reach a repo, HTML or JS before its `topic_release` time.

## Commands

```bash
composer install && npm install
composer test          # Pint, PHPStan level 9 and Pest
composer lint:fix      # apply Pint formatting
npm test               # Pages Function
npm run test:browser   # Playwright
```

## Layout

- `config/jam.php` and `config/presets/`: defaults and presets, merged with the site's settings by `src/JamConfig.php`.
- `src/JamData.php`: reads `_data/`, and removes a topic before its release.
- `src/JamSchedule.php`: upcoming jam and topic release logic.
- `resources/views/components/project/`: one partial per project section.
- `resources/functions/api/`: the Cloudflare Pages Function, copied into sites by `PrepareFunctionsBuildTask`.
- `tests/Fixtures/`: a skeleton site plus one data set per preset, built for real by the tests.
- `.github/workflows/site.yml`: the reusable workflow that all sites deploy with.

## Things to watch

- Hyde catches exceptions in build tasks and keeps building. A task that must stop the build has to end the process itself, see `AValidateDataBuildTask`.
- Compare changes against the Game Jam site with `scripts/compare-build.php` on its `engine-migration` branch.

## Commits

Commit only under the maintainer's Git identity. Never set Claude as author and never add a `Co-Authored-By` trailer.
