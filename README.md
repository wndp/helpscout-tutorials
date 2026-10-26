# HelpScout Tutorials

A Laravel toolchain for maintaining HelpScout Docs articles from a repo.

Ships six artisan commands (`tutorials:download`, `tutorials:publish`, `tutorials:create`, plus `tutorials:categories`, `tutorials:category`, and `tutorials:categorize` for managing categories) plus a one-shot installer that links matching Claude Code skills into `.claude/skills/` so the same workflow is driveable from Claude Code.

## Install

Add it as a dev dependency — this is a tooling package, not something your app needs at runtime:

```sh
composer require --dev wndp/helpscout-tutorials
php artisan tutorials:install
```

The installer:
- Publishes `config/helpscout-tutorials.php`
- Adds `HELPSCOUT_DOCS_API_KEY=` to your `.env` if missing
- Adds `/storage/helpscout-tutorials` to `.gitignore` if missing
- Symlinks the Claude Code skills into `.claude/skills/`

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

### `tutorials:categories`

Lists the categories in a collection along with their IDs (resolved from the local manifest, so run `tutorials:download` first).

```sh
php artisan tutorials:categories YourCollection
```

### `tutorials:category`

Creates, updates, or deletes a category. The first argument is the action.

```sh
php artisan tutorials:category create --collection=YourCollection --name="How-tos" --visibility=public
php artisan tutorials:category update --id={category_id} --name="Renamed" --order=2
php artisan tutorials:category delete --id={category_id}
```

Options: `--name`, `--slug`, `--visibility` (public/private), `--order` (int), `--default-sort` (popularity/name), `--dry-run`. For `update`, omit `--name` to keep the current name — the command fetches it for you. Deleting a category moves its articles to HelpScout's "Uncategorized"; it does not delete the articles.

### `tutorials:categorize`

Sets the categories an article belongs to within its collection. This **replaces** the article's category list with exactly the ones you pass; values are matched against category name, slug, or ID.

```sh
php artisan tutorials:categorize {article_id} --category="How-tos" --category="FAQ"
php artisan tutorials:categorize {article_id} --category={category_id} --dry-run
```

## Claude Code skills

The skills live in this package under `resources/claude-skills/`. The installer symlinks each one into your app's `.claude/skills/` with a relative link (e.g. `.claude/skills/tutorials-review → ../../vendor/wndp/helpscout-tutorials/resources/claude-skills/tutorials-review`), so Claude Code reads them straight from `vendor/`. Nothing is copied: a `composer update` changes the skills along with the artisan commands, and each app uses the skills matching its installed package version.

The links are relative, so you can commit them and teammates get the skills after `composer install`. Re-run `php artisan tutorials:install` after updating the package if a release adds or renames a skill; it is safe to re-run and removes links to skills that no longer exist. If symlinks aren't available (e.g. Windows without developer mode), the installer copies the skills instead and warns you.

Four skills are linked:

- `/tutorials-review [feature]` — compares the articles against the current code and writes `*.proposed.html` updates. Give it a feature (e.g. `/tutorials-review team invitations`) to work on just that feature: it updates the matching article, or drafts a new one if none exists, and then offers to post it to HelpScout
- `/tutorials-coverage` — audits which user-facing surfaces have no tutorial and writes a prioritized gap report
- `/tutorials-publish [article or file]` — per-article confirmation loop that publishes `*.proposed.html` edits (`tutorials:publish`), creates articles from `*.draft.html` files (`tutorials:create`), and unpublishes articles
- `/tutorials-categories` — manages categories (`tutorials:categories`, `tutorials:category`) and sets which categories an article belongs to (`tutorials:categorize`)

Each skill runs `tutorials:download` itself when the local copy is missing (`/tutorials-review` also refreshes it when it's more than a day old), so there's no separate download step. `/tutorials-publish` and `/tutorials-categories` work in any project as-is. They use `php artisan` and tell Claude to switch to `vendor/bin/sail artisan` in Sail projects.

`/tutorials-review` and `/tutorials-coverage` reason about *your* codebase, so they're written for a **Laravel + Inertia (Vue)** app — backend in `app/`, routes in `routes/web.php`, UI under `resources/js/`. The directory paths are concrete; the only things that vary per app are a few identifiers (your primary layout/nav component, permission props, custom route prefixes), and each skill has a note near the top calling those out to confirm.

## Config

`config/helpscout-tutorials.php`:

```php
return [
    'api_key' => env('HELPSCOUT_DOCS_API_KEY'),
    'storage_path' => env('HELPSCOUT_TUTORIALS_PATH', 'helpscout-tutorials'),
    'default_collection' => env('HELPSCOUT_DEFAULT_COLLECTION'),
];
```

If `default_collection` is set, `tutorials:download`, `tutorials:create`, and `tutorials:category create` will use it when `--collection` is not passed.

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

## License

MIT. See [LICENSE](LICENSE).
