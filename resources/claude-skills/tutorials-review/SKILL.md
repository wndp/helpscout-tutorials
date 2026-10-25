---
name: tutorials-review
description: Review downloaded HelpScout tutorials against the current codebase and propose updates.
---

You are running phase 2 of the HelpScout tutorial maintenance workflow: **review**.

## What this does

For each downloaded article, decide whether it is still accurate given the **current state of this codebase** (UI, routes, features, screens, terminology). If not, write a proposed replacement to `*.proposed.html` alongside the original, plus a `*.notes.md` explaining what changed and why.

**No HelpScout API calls happen in this phase.** All output is local files.

This command assumes a Laravel + Inertia (Vue) app: backend in `app/`, routes in `routes/web.php`, and the UI in `resources/js/`.

## Prerequisites

- The storage directory is `storage/helpscout-tutorials/` unless `HELPSCOUT_TUTORIALS_PATH` overrides it (check with `php artisan config:show helpscout-tutorials`). Paths below are relative to it.
- `manifest.json` must exist. If it doesn't, tell the user to run `/tutorials-download` first and stop.

## Steps

1. Read `manifest.json` to get the list of articles.
2. Get a sense of recent application changes:
   - `git log --since="6 months ago" --pretty=format:"%h %ad %s" --date=short`
   - Skim `app/Http/Controllers/`, `resources/js/Pages/`, `resources/js/Components/`, and `routes/web.php` when needed to understand current behavior. Use Explore subagents for breadth.
3. For each article in the manifest (process them in collection order):
   a. Read the article's `*.original.html` file.
   b. Identify what the article is about — match it to a current code area using its title, slug, and inline references (route names, page names, feature names).
   c. Compare what the article describes to current reality. Specifically check for:
      - **Removed or renamed features** — if the article references a button, page, or workflow that no longer exists, that section is stale.
      - **New UI** — if a feature gained capabilities (filters, exports, new fields), the article likely needs an additional section or a screenshot note.
      - **Renamed terminology** — e.g., a column header that was renamed.
      - **Changed routes / URLs** — anything the article links to that no longer resolves.
      - **Outdated workflow steps** — e.g., the article says "click the X button on Y page" but X is now a different control.
   d. If the article is **still accurate**, do nothing — do not write a `.proposed.html`.
   e. If the article **needs updates**, write:
      - `*.proposed.html` next to the original, containing the full updated HTML body. Preserve the original's HTML formatting style (heading levels, lists, inline styling) — change only the content that needs changing. Do not strip screenshots or video embeds; if a screenshot is now wrong, leave the `<img>` in place and add a note in `.notes.md` that the screenshot needs to be re-captured manually.
      - `*.notes.md` summarizing: what changed in the app, which sections of the article were updated, anything the human reviewer should double-check (especially screenshots and external links).
4. When done, produce a final summary table:
   - Articles reviewed
   - Articles with proposed updates
   - Articles that need manual screenshot re-capture (extracted from notes)
   - Articles skipped (still accurate)
5. Suggest the user run `/tutorials-publish` next.

## Guardrails

- **Be conservative.** If you are not confident a section is wrong, leave it alone and call it out in notes. False positives waste the reviewer's time.
- **Do not rewrite for tone or style.** Only change content that is factually out of date.
- **Never edit `.original.html` files.** Only ever write `.proposed.html` and `.notes.md`.
- **Do not make HelpScout API calls.** This phase is read-only against the local files + git + code.
- If you find an article that references a feature you cannot locate in the code at all, note it as `STATUS: NEEDS HUMAN REVIEW` in the notes file and do not write a `.proposed.html` for it.
