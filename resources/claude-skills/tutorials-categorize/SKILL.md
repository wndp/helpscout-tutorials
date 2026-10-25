---
name: tutorials-categorize
description: Set which categories a HelpScout article belongs to within its collection.
---

You are assigning an article to one or more **categories** within its collection.

## What this does

Sets the full list of categories an article belongs to and `PUT`s it to HelpScout via `php artisan tutorials:categorize`. This is **replace** semantics: the article ends up in exactly the categories you pass, and any categories it had before that you don't list are removed.

## Prerequisites

- A `manifest.json` must exist in the configured storage directory (run `/tutorials-download` first if not). The article must be in it so its collection can be resolved.
- `HELPSCOUT_DOCS_API_KEY` must be set in `.env`.

## Steps

1. Identify the article (by ID, or by name from `manifest.json`). Confirm the article name and its collection with the user.
2. List the categories available in that article's collection so the user can choose by name:
   ```
   php artisan tutorials:categories {collection}
   ```
3. Ask the user which categories the article should belong to. They can name categories, slugs, or IDs — the command resolves all three against the collection.
4. Preview with a dry run, then show the user what will be sent:
   ```
   php artisan tutorials:categorize {article_id} --category="How-tos" --category="FAQ" --dry-run
   ```
5. On approval, run the same command without `--dry-run`:
   ```
   php artisan tutorials:categorize {article_id} --category="How-tos" --category="FAQ"
   ```
6. Report the result.

## Guardrails

- **This replaces the article's categories, it does not append.** If the user wants to *add* a category while keeping the existing ones, first list the article's current categories and include them in the `--category` flags so they aren't dropped. (The HelpScout Docs API only exposes whole-list replacement.)
- **Every `--category` value must resolve** to a category in the article's collection (by name, slug, or ID). If one doesn't, the command fails and lists nothing — run `tutorials:categories` to show valid options and re-confirm.
- An article and its categories must live in the **same collection**. You cannot assign a category from another collection.
- Use `--dry-run` to preview the resolved category IDs before committing.
- If the command returns a non-zero exit code, report the error verbatim and ask how to proceed.

If this project uses Laravel Sail, run `vendor/bin/sail artisan` instead of `php artisan`.
