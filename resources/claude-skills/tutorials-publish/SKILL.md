---
name: tutorials-publish
description: Push local tutorial changes to HelpScout — publish edits to existing articles, create new articles from drafts, or unpublish an article — one article at a time with per-article confirmation.
argument-hint: "[article or file]"
---

You are running the publish phase of the HelpScout tutorial maintenance workflow. It sends local changes to HelpScout:

- **Edits** — an existing article with a `*.proposed.html` next to its `*.original.html` is `PUT` via `php artisan tutorials:publish`.
- **New articles** — a `*.draft.html` (conventionally `{collection-slug}/new/{slug}.draft.html`) is `POST`ed via `php artisan tutorials:create`.
- **Unpublishing** — an existing article is taken down via `php artisan tutorials:publish --status=notpublished`.

Limit to: $ARGUMENTS

If an article (by name or ID) or a file is named above, work on only that item. Otherwise process everything pending.

## Prerequisites

- `HELPSCOUT_DOCS_API_KEY` must be set in `.env`. If not, stop and ask the user to add it — do NOT prompt for the key in chat or write it to any file.
- The storage directory is `storage/helpscout-tutorials/` unless `HELPSCOUT_TUTORIALS_PATH` overrides it (check with `php artisan config:show helpscout-tutorials`). Paths below are relative to it.
- `manifest.json` must exist so article and collection IDs can be resolved. If it doesn't, run `php artisan tutorials:download` (pass `--collection=...` if no `default_collection` is configured and the user named one). Re-downloading overwrites `*.original.html` and `manifest.json` only; `*.proposed.html`, `*.draft.html`, and `*.notes.md` are left alone.

## Steps

1. Read `manifest.json` and build two lists (filtered to the named item, if any):
   - **Edits:** articles that have a non-empty `*.proposed.html`.
   - **Drafts:** every non-empty `*.draft.html` under the storage directory.
   If both are empty and the user didn't ask to unpublish something, tell them to run `/tutorials-review` (or write a draft) first and stop.
2. Dry-run the first item to sanity-check config and the payload:
   ```
   php artisan tutorials:publish {article_id} --dry-run
   php artisan tutorials:create {draft-path} --name="..." --collection=... --dry-run
   ```
   If that fails, stop and report the error.
3. **Edits**, in collection order. For each article:
   a. Show the article name, ID, collection, and its `*.notes.md` if present.
   b. Show the diff: `git diff --no-index --word-diff=color <original> <proposed>` (or `diff -u`). Truncate to ~150 lines and offer to show more.
   c. Ask with `AskUserQuestion`: **Publish** (`php artisan tutorials:publish {article_id}`), **Skip**, **Show full diff** (then re-ask), or **Stop**.
4. **Drafts**. For each draft:
   a. Show the path and its sibling `*.notes.md` if present.
   b. Pick the title (`--name`) from the notes; otherwise ask. Omit `--slug` unless the user wants a specific URL. Confirm the target collection resolves in the manifest.
   c. Ask with `AskUserQuestion`: **Create (published)**, **Create (notpublished)** (soft launch), **Skip**, **Show full draft** (then re-ask), or **Stop**.
   d. On create, run `php artisan tutorials:create {draft-path} --name="..." --collection=... --status=...` and report the new article ID.
5. Print a summary: published, created (with IDs), skipped, errors. Remind the user to verify in the HelpScout admin UI. If anything was created, run `php artisan tutorials:download` so the new articles join the manifest.

## Unpublishing an article

If the user wants to take an article *down* (e.g. the feature was removed), no proposed file is needed:

```
php artisan tutorials:publish {article_id} --status=notpublished
```

This is reversible with `--status=published`. Offer it as the default removal mode; permanent deletion isn't supported here and must be done in the HelpScout admin UI.

## Guardrails

- **One article per request.** Never batch updates or creations.
- **Never send an empty file.** The artisan commands guard against a missing or empty `*.proposed.html`; check `*.draft.html` size yourself.
- **Article names must be unique within a collection.** If a create fails with a name collision, ask the user for another title.
- **If the user picks "Stop", send nothing further** in this run, even if you've already shown later diffs.
- **Do not delete `*.proposed.html` or `*.draft.html` after sending.** They are useful for diffing later.
- If a command returns a non-zero exit code, report the error verbatim and ask how to proceed before continuing.
- If this project uses Laravel Sail, run `vendor/bin/sail artisan` instead of `php artisan`.
