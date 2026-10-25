---
name: tutorials-create
description: Create a new HelpScout Docs article from a local *.draft.html file (used for tutorials that do not exist in HelpScout yet).
---

You are creating a brand-new HelpScout Docs article from a local draft. Use this when the article does not yet exist in HelpScout — for established articles, use `/tutorials-publish` instead.

## What this does

Reads a `*.draft.html` file from the configured storage directory, looks up the target collection by name in the manifest, and `POST`s a new article via `php artisan tutorials:create`. On success, the new article has a HelpScout ID and is part of the public docs site (or unpublished, depending on the chosen status).

## Prerequisites

- A `manifest.json` must exist in the configured storage directory so the collection name can be resolved to a collection ID. If it does not, tell the user to run `/tutorials-download` first.
- The draft file must exist somewhere under the storage directory. Conventionally drafts live in `{collection-slug}/new/{slug}.draft.html` with a sibling `{slug}.notes.md`.
- `HELPSCOUT_DOCS_API_KEY` must be set in `.env`.

## Steps

1. Read `manifest.json` to confirm the collection name resolves.
2. Enumerate every `*.draft.html` under the storage directory. Each one is a candidate.
3. For each draft, present it to the user with the contents of its sibling `*.notes.md` (if present) and ask via `AskUserQuestion`:
   - **Create (published)** — call `tutorials:create` with status `published`.
   - **Create (notpublished)** — call `tutorials:create` with status `notpublished` (soft launch).
   - **Skip** — leave it as a local draft and move on.
   - **Show full draft** — print the full HTML, then re-ask.
   - **Stop** — halt the loop.
4. Before each Create, run a `--dry-run` and confirm the payload size and target collection look right. If it fails (e.g., manifest missing, file missing), stop and report the error.
5. On Create, capture the new article ID from the command output. Report it back to the user.
6. After all drafts are processed, recommend re-running `/tutorials-download` so the manifest picks up the new articles and they become eligible for `/tutorials-publish` edits in the future.

## Command shape

```
php artisan tutorials:create {file-relative-to-storage-path-or-absolute} \
  --name="Article Title" \
  --collection=YourCollection \
  [--status=published|notpublished] \
  [--slug=optional-slug] \
  [--dry-run]
```

## How to pick the article title and slug

- **Title (`--name`)**: prefer the title suggested in the sibling `*.notes.md`. HelpScout requires article names to be unique within a collection — if creation fails with a name-collision error, ask the user to pick an alternative.
- **Slug**: omit `--slug` and let HelpScout generate one from the title. Only pass `--slug` if the user explicitly wants a different URL.

## Guardrails

- **One article per `POST`.** Do not batch creations.
- **Never create an article whose `*.draft.html` is empty.** Inspect the file size; if it's zero bytes, stop and tell the user.
- **Do not delete the local `*.draft.html` file after creation.** Future downloads will fetch the new article as a `*.original.html` so diffing remains possible.
- If `tutorials:create` returns a non-zero exit code, report the error verbatim and ask the user how to proceed.

If this project uses Laravel Sail, run `vendor/bin/sail artisan` instead of `php artisan`.
