---
name: tutorials-categories
description: Manage HelpScout Docs categories — list, create, rename, or delete categories in a collection, and set which categories an article belongs to.
---

You are managing **categories** for a HelpScout Docs collection: the categories themselves (via `php artisan tutorials:category`) and which categories an article belongs to (via `php artisan tutorials:categorize`).

## Prerequisites

- `HELPSCOUT_DOCS_API_KEY` must be set in `.env`. If not, stop and ask the user to add it — do NOT prompt for the key in chat or write it to any file.
- `manifest.json` in the storage directory (`storage/helpscout-tutorials/` unless `HELPSCOUT_TUTORIALS_PATH` overrides it) must exist so collection names and articles resolve to IDs. If it doesn't, or the collection or article the user wants isn't in it, run `php artisan tutorials:download` (pass `--collection=...` if no `default_collection` is configured). Re-downloading overwrites `*.original.html` and `manifest.json` only.

## Steps

1. Work out the collection from the user's message, or ask. Collections are listed in `manifest.json`.
2. List its categories so the user can see names and IDs:
   ```
   php artisan tutorials:categories {collection}
   ```
3. Ask what they want to do with `AskUserQuestion` (skip this if their message already says):
   - **Create** — collect a name (required, unique in the collection) and any of `--slug`, `--visibility` (public/private), `--order`, `--default-sort` (popularity/name):
     ```
     php artisan tutorials:category create --collection={collection} --name="..." [--visibility=...] [...]
     ```
   - **Update** — pick the category ID from the listing and pass only the fields that change (the current name is filled in when `--name` is omitted):
     ```
     php artisan tutorials:category update --id={category_id} [--name="..."] [--slug=...] [--visibility=...] [--order=...] [--default-sort=...]
     ```
   - **Delete** — confirm with the user first:
     ```
     php artisan tutorials:category delete --id={category_id}
     ```
   - **Assign an article** — identify the article (by ID, or by name from `manifest.json`) and confirm its name and collection. Ask which categories it should belong to (names, slugs, or IDs all resolve), dry-run, show the result, then run it on approval:
     ```
     php artisan tutorials:categorize {article_id} --category="How-tos" --category="FAQ" --dry-run
     php artisan tutorials:categorize {article_id} --category="How-tos" --category="FAQ"
     ```
   - **Stop** — end the session.
4. After each change, re-list the categories (or report the article's new categories) and ask if there's anything else.

## Guardrails

- **Resolve collections and articles from the manifest; never guess an ID.**
- **Assigning replaces, it does not append.** The article ends up in exactly the categories passed. To *add* one, include the article's current categories too (the HelpScout API only supports whole-list replacement).
- **An article's categories must be in its own collection.** Every `--category` must resolve there, or the command fails without changing anything — re-list and re-confirm.
- **Deleting a category is destructive.** Its articles fall back to HelpScout's "Uncategorized" pseudo-category (the articles themselves are kept). Always confirm, and offer `--dry-run` first if the user is unsure.
- **Category names must be unique within their collection.** On a uniqueness error, report it verbatim and ask for another name.
- Any command accepts `--dry-run` to preview the request without calling the API.
- If a command returns a non-zero exit code, report the error verbatim and ask how to proceed.
- If this project uses Laravel Sail, run `vendor/bin/sail artisan` instead of `php artisan`.
