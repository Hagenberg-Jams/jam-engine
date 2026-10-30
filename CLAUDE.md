# Jam Engine

Composer package with the shared HydePHP code for the Hagenberg jam websites: build tasks, commands, data access, views and frontend sources. See `README.md` for how sites consume it.

## Rules

- No jam-specific content in this repo: no jam names, URLs, sponsors or texts. These belong in the site repos.
- Express differences between jam types through configuration. Views must not check for a specific jam type.
- Public URLs of existing sites must stay stable: `/YYYY`, `/YYYY/<slug>`, `/people`, `/person/<slug>`, `/media/...` and `/games/YYYY/...`.
- A jam's topic must never reach a repo, HTML or JS before its `topic_release` time.

## Commands

```bash
composer install
composer test       # Pint check + PHPStan level 9
composer lint:fix   # apply Pint formatting
```

## Commits

Commit only under the maintainer's Git identity. Never set Claude as author and never add a `Co-Authored-By` trailer.
