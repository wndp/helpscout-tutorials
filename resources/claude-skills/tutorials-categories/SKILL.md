---
name: tutorials-categories
description: Manage HelpScout Docs categories — list, create, rename, or delete categories within a collection.
---

You are managing **categories** for a HelpScout Docs collection.

## What this does

Lists the categories in a collection and lets the user create, update (rename / change slug, visibility, order, default sort), or delete them. Each mutation goes through `php artisan tutorials:category` with a confirmation step; deletes are confirmed explicitly because they are destructive.

## Prerequisites

- A `manifest.json` must exist in the configured storage directory (run `/tutorials-download` first if not) so the collection name/slug can be resolved to a collection ID.
- `HELPSCOUT_DOCS_API_KEY` must be set in `.env`.

## Steps

1. Ask the user which collection they want to manage (or take it from their message). Collections are listed in `manifest.json`.
2. List the current categories so the user can see IDs:
   ```
   php artisan tutorials:categories {collection}
   ```
   Show the table. The **ID** column is what the update/delete commands need.
3. Ask the user what they want to do, using `AskUserQuestion`:
   - **Create** — collect a name (required, must be unique in the collection) and any of `--slug`, `--visibility` (public/private), `--order`, `--default-sort` (popularity/name). Then run:
     ```
     php artisan tutorials:category create --collection={collection} --name="..." [--visibility=...] [...]
     ```
   - **Update** — pick the category ID from the listing, collect the fields to change, then run:
     ```
     php artisan tutorials:category update --id={category_id} [--name="..."] [--slug=...] [--visibility=...] [--order=...] [--default-sort=...]
     ```
     You only need to pass the fields that change; the command fills in the current name automatically when you omit `--name`.
   - **Delete** — confirm with the user first, then run:
     ```
     php artisan tutorials:category delete --id={category_id}
     ```
   - **Stop** — end the session.
4. After each mutation, re-run `tutorials:categories {collection}` to show the updated list, and ask if there's anything else.

## Guardrails

- **Resolve the collection from the manifest, never guess a collection ID.** If the collection isn't in `manifest.json`, tell the user to run `/tutorials-download` and stop.
- **Deleting a category is destructive** — its articles fall back to HelpScout's "Uncategorized" pseudo-category (the articles themselves are not deleted). Always confirm before running a delete, and offer `--dry-run` first if the user is unsure.
- **Category names must be unique within their collection.** If a create/update fails with a uniqueness error, report it verbatim and let the user pick a different name.
- Use `--dry-run` on any command to preview the request without calling the API.
- If a command returns a non-zero exit code, report the error verbatim and ask how to proceed.

If this project uses Laravel Sail, run `vendor/bin/sail artisan` instead of `php artisan`.
