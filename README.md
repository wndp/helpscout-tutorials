# HelpScout Tutorials

A Laravel toolchain for maintaining HelpScout Docs articles from a repo.

Ships three artisan commands (`tutorials:download`, `tutorials:publish`, `tutorials:create`) plus a one-shot installer that also drops Claude Code slash-command templates into `.claude/commands/` so the same workflow is driveable from Claude Code.

## Install

This package is private. Add the repository to your project's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:wndp/helpscout-tutorials.git" }
]
```

Then add it as a dev dependency — this is a tooling package, not something your app needs at runtime:

```sh
composer require --dev wndp/helpscout-tutorials
php artisan tutorials:install
```

The installer:
- Publishes `config/helpscout-tutorials.php`
- Adds `HELPSCOUT_DOCS_API_KEY=` to your `.env` if missing
- Adds `/storage/helpscout-tutorials` to `.gitignore` if missing
- Copies the slash-command templates to `.claude/commands/`

Get a HelpScout Docs API key from your HelpScout account and set it in `.env`:

```
HELPSCOUT_DOCS_API_KEY=your-key-here
```

## Artisan commands

### `tutorials:download`

Fetches all published articles in the matching collection(s) and writes them to `storage/helpscout-tutorials/` with a `manifest.json`.

```sh
php artisan tutorials:download --collection=YourCollection
```

Options: `--collection=*` (repeatable, matches collection name or slug case-insensitively; omit to fetch all collections), `--path=` (storage subdirectory).

### `tutorials:publish`

`PUT`s a single article's `*.proposed.html` back to HelpScout. Also supports unpublishing via `--status=notpublished` with no proposed file required.

```sh
php artisan tutorials:publish {article_id}
php artisan tutorials:publish {article_id} --status=notpublished
php artisan tutorials:publish {article_id} --dry-run
```

### `tutorials:create`

`POST`s a new article from a local `*.draft.html`.

```sh
php artisan tutorials:create path/to/file.draft.html \
  --name="Article Title" \
  --collection=YourCollection \
  --status=published
```

## Claude Code slash commands

The installer copies five slash-command templates to `.claude/commands/`:

- `/tutorials-download` — wraps `tutorials:download`
- `/tutorials-publish` — per-article confirmation loop around `tutorials:publish`
- `/tutorials-create` — per-draft confirmation loop around `tutorials:create`
- `/tutorials-review` — compares downloaded articles against the current code and writes `*.proposed.html` updates
- `/tutorials-coverage` — audits which user-facing surfaces have no tutorial and writes a prioritized gap report

The first three are pure wrappers around the artisan commands and work in any project as-is. The templates use `php artisan`; if your project uses Sail, adjust to `vendor/bin/sail artisan` after publishing.

`/tutorials-review` and `/tutorials-coverage` reason about *your* codebase, so they're written for a **Laravel + Inertia (Vue)** app — backend in `app/`, routes in `routes/web.php`, UI under `resources/js/`. The directory paths are concrete; the only things that vary per app are a few identifiers (your primary layout/nav component, permission props, custom route prefixes), and each command has a note near the top calling those out to confirm.

## Config

`config/helpscout-tutorials.php`:

```php
return [
    'api_key' => env('HELPSCOUT_DOCS_API_KEY'),
    'storage_path' => env('HELPSCOUT_TUTORIALS_PATH', 'helpscout-tutorials'),
    'default_collection' => env('HELPSCOUT_DEFAULT_COLLECTION'),
];
```

If `default_collection` is set, `tutorials:download` and `tutorials:create` will use it when `--collection` is not passed.

## Storage layout

```
storage/{storage_path}/
├── manifest.json
└── {collection-slug}/
    ├── {article-slug}.original.html      # Source of truth from HelpScout
    ├── {article-slug}.proposed.html      # (Optional) edits awaiting publish
    └── {article-slug}.notes.md           # (Optional) reviewer notes
```

For new articles that don't exist in HelpScout yet, conventionally put drafts under `{collection-slug}/new/{slug}.draft.html` with a sibling `{slug}.notes.md`.

## Tests

```sh
composer install
vendor/bin/phpunit
```

Tests use Orchestra Testbench so no real Laravel app is required.
