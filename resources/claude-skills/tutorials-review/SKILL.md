---
name: tutorials-review
description: Review HelpScout tutorials against the current codebase and propose updates — either every article, or the article(s) for one feature (drafting a new article if none exists, then optionally posting it to HelpScout).
argument-hint: "[feature]"
---

You are running the review phase of the HelpScout tutorial maintenance workflow.

Feature requested: $ARGUMENTS

## What this does

Decides whether articles are still accurate given the **current state of this codebase** (UI, routes, features, screens, terminology), and writes proposed changes as local files next to the downloaded originals. It runs in one of two modes:

- **Full review** (no feature given above): check every downloaded article and propose updates where needed.
- **Feature mode** (a feature is given above): find the article(s) covering that feature and update them, or draft a new article if none exists. Then offer to post the result to HelpScout.

This skill assumes a Laravel + Inertia (Vue) app: backend in `app/`, routes in `routes/web.php`, and the UI in `resources/js/`.

## Prerequisites

- The storage directory is `storage/helpscout-tutorials/` unless `HELPSCOUT_TUTORIALS_PATH` overrides it (check with `php artisan config:show helpscout-tutorials`). Paths below are relative to it.
- `manifest.json` must exist and be current. If it is missing, or its `downloaded_at` is more than a day old, run `php artisan tutorials:download` first (pass `--collection=...` if no `default_collection` is configured and the user named one). This needs `HELPSCOUT_DOCS_API_KEY` in `.env` — if it's missing, stop and ask the user to add it; never ask for the key in chat. Re-downloading overwrites `*.original.html` and `manifest.json` only; `*.proposed.html`, `*.draft.html`, and `*.notes.md` are left alone. On HTTP 401 the key is invalid; on HTTP 429 the rate limit was hit (the client already retried) — report it and stop.

## How to update an article

Whenever an article needs changes, write two files next to its `*.original.html`:

- `*.proposed.html` — the full updated HTML body. Preserve the original's HTML formatting style (heading levels, lists, inline styling) and change only what needs changing. Do not strip screenshots or video embeds; if a screenshot is now wrong, leave the `<img>` in place and note in `*.notes.md` that it needs re-capturing.
- `*.notes.md` — what changed in the app, which sections were updated, and anything the reviewer should double-check (especially screenshots and external links).

When comparing an article to the code, look for:
- **Removed or renamed features** — buttons, pages, or workflows that no longer exist.
- **New UI** — capabilities the feature gained (filters, exports, new fields) that the article doesn't mention.
- **Renamed terminology** — e.g. a column header that was renamed.
- **Changed routes / URLs** — links that no longer resolve.
- **Outdated workflow steps** — e.g. "click X on page Y" when X is now a different control.

## Full review

1. Read `manifest.json` to get the list of articles.
2. Get a sense of recent application changes:
   - `git log --since="6 months ago" --pretty=format:"%h %ad %s" --date=short`
   - Skim `app/Http/Controllers/`, `resources/js/Pages/`, `resources/js/Components/`, and `routes/web.php` when needed. Use Explore subagents for breadth.
3. For each article, in collection order: read its `*.original.html`, match it to a code area by its title, slug, and inline references, and compare. If it is still accurate, write nothing. If it needs updates, write the files described above.
4. Produce a summary table: articles reviewed, articles with proposed updates, articles needing screenshot re-capture (from the notes), and articles skipped as still accurate.
5. Suggest the user run `/tutorials-publish` next.

## Feature mode

1. **Find the feature in the code.** Use an Explore subagent to locate its routes, controllers, pages, components, and permission gates. Note the user-visible names (page titles, button labels, menu items) — articles use those, not class names. If the feature can't be found or the request is ambiguous, ask the user before going further.
2. **Find the articles that cover it.** Search the manifest's article names and slugs, and grep the `*.original.html` files for the user-visible names. Show the user what you found and confirm which article(s) to work on.
3. **If an article exists:** compare it to the code and write `*.proposed.html` + `*.notes.md` as described above. Because the user asked about this feature, it's fine to add sections for capabilities the article is missing, not just fix what's wrong. Still don't rewrite unrelated parts for tone or style. If the article turns out to be accurate and complete, say so and stop.
4. **If no article exists:** confirm with the user that a new article is wanted, which collection it belongs in (the configured `default_collection`, or ask), and the title. Then:
   - Read two or three existing `*.original.html` files from that collection and match their structure and HTML style (heading levels, intro paragraph, numbered steps, callouts).
   - Write `{collection-slug}/new/{slug}.draft.html` with a user-facing walkthrough of the feature: what it's for, who can use it (permissions), and step-by-step instructions using the exact UI labels from the code.
   - Do not invent screenshots or `<img>` tags. List the screenshots worth capturing in the notes.
   - Write `{collection-slug}/new/{slug}.notes.md` with the suggested title, the target collection, the code the article is based on (file paths), and the screenshots to capture.
5. **Show the result.** For an update, show the diff (`git diff --no-index --word-diff=color <original> <proposed>`, or `diff -u`). For a new article, show the draft. Then ask with `AskUserQuestion`:
   - **Post to HelpScout now** — for a new article, also ask whether it should be published or created as notpublished (a soft launch).
   - **Keep it local** — leave the files for review and post later with `/tutorials-publish`.
   - **Revise** — take the user's feedback, update the files, and ask again.
6. **Posting** follows the same rules as `/tutorials-publish`, for these files only:
   - Run a dry run first and stop if it fails: `php artisan tutorials:publish {article_id} --dry-run`, or `php artisan tutorials:create {draft-path} --name="..." --collection=... --status=... --dry-run`.
   - Then run the same command without `--dry-run`. One article per request.
   - After a create, report the new article ID and run `php artisan tutorials:download` so it joins the manifest.
   - Report the result and remind the user to check the article in the HelpScout admin UI.

## Guardrails

- **Be conservative.** If you're not confident a section is wrong, leave it alone and call it out in the notes. False positives waste the reviewer's time.
- **Do not rewrite for tone or style.** Only change content that is out of date or, in feature mode, missing.
- **Never edit `*.original.html` files.** Only write `*.proposed.html`, `*.draft.html`, and `*.notes.md`.
- **No HelpScout API calls other than the download above** — except in feature mode, posting the article the user just approved. Never post in a full review.
- If an article references a feature you can't locate in the code at all, note it as `STATUS: NEEDS HUMAN REVIEW` in the notes file and don't write a `*.proposed.html` for it.
- If a command returns a non-zero exit code, report the error verbatim and ask how to proceed.
- If this project uses Laravel Sail, run `vendor/bin/sail artisan` instead of `php artisan`.
