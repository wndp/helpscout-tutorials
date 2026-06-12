---
description: Publish proposed tutorial updates back to HelpScout, one article at a time with per-article confirmation.
---

You are running phase 3 of the HelpScout tutorial maintenance workflow: **publish**.

## What this does

For each article that has a `*.proposed.html` file alongside its `*.original.html`, show the diff, ask the user to publish / skip / open-in-editor, and on approval `PUT` the new content to HelpScout via `php artisan tutorials:publish`.

## Prerequisites

- A `manifest.json` must exist in the configured storage directory (run `/tutorials-download` first if not).
- At least one article must have a `*.proposed.html` file. If none do, tell the user to run their review step first and stop.
- `HELPSCOUT_DOCS_API_KEY` must be set in `.env`.

## Steps

1. Read `manifest.json` to enumerate articles.
2. Build a list of articles that have a `*.proposed.html` (skip those that don't).
3. Do a quick dry-run sanity check on the first article:
   ```
   php artisan tutorials:publish {first_article_id} --dry-run
   ```
   If that fails, stop and report the error.
4. Iterate the articles in collection order. For each one:
   a. Show the article name, ID, collection, and `*.notes.md` content if present.
   b. Show the diff between `.original.html` and `.proposed.html`. Use `git diff --no-index --word-diff=color <original> <proposed>` or `diff -u`. Truncate to ~150 lines and offer to show more if asked.
   c. Use `AskUserQuestion` with options:
      - **Publish** — call `php artisan tutorials:publish {article_id}` and report the result.
      - **Skip** — move to the next article. Do not call the API.
      - **Show full diff** — print the full diff, then re-ask.
      - **Stop** — halt the loop, do nothing further.
   d. After publish, continue to the next article.
5. At the end, print a summary: articles published, articles skipped, any errors. Remind the user to manually verify in the HelpScout admin UI.

## Unpublishing an article

If the user wants to take an article *down* (e.g. the underlying feature was removed), use the same command with `--status=notpublished` and no proposed file required:

```
php artisan tutorials:publish {article_id} --status=notpublished
```

This is reversible — re-publish later with `--status=published`. Always offer this as the default removal mode unless the user explicitly asks to delete the article from HelpScout permanently (which this workflow does not support — they would need to do that in the HelpScout admin UI).

## Guardrails

- **One article per `PUT`.** Do not batch updates. Each one goes through the artisan command with its specific article ID.
- **Never publish an article whose `*.proposed.html` is missing or empty.** The artisan command guards against this; respect the failure.
- **If the user picks "Stop", do not publish anything further** in this run, even if you've already shown diffs for later articles.
- **Do not delete `*.proposed.html` files after publishing.** They are useful for diffing later.
- If `tutorials:publish` returns a non-zero exit code, report the error verbatim and ask the user how to proceed before continuing to the next article.

## Note for Laravel Sail projects

If this project uses Laravel Sail, prefix each command with `vendor/bin/sail` instead of running `php` directly:
`vendor/bin/sail artisan tutorials:publish ...`
