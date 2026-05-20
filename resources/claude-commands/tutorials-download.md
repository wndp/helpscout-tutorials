---
description: Download all published HelpScout Docs tutorials into local storage for review.
---

You are running phase 1 of the HelpScout tutorial maintenance workflow: **download**.

## What this does

Pulls every published article from the configured collection(s) into the configured storage path (default `storage/helpscout-tutorials/`). This directory should be gitignored.

## Steps

1. Confirm `HELPSCOUT_DOCS_API_KEY` is set in `.env`. If not, stop and ask the user to add it — do NOT prompt for the key in chat or write it to any file.
2. Run the artisan command. If the user has a `default_collection` configured, no `--collection` flag is needed; otherwise pass the collection the user wants:
   ```
   php artisan tutorials:download
   php artisan tutorials:download --collection=YourCollection
   ```
3. After it completes, read `manifest.json` in the configured storage directory and report:
   - Total collections + total articles downloaded
   - List of collections with article counts
   - The oldest and newest `updated_at` across all articles
4. Suggest the user run `/tutorials-review` (if they have one) or `/tutorials-publish` next.

## Notes

- If the command fails with HTTP 401, the API key is invalid or missing. Tell the user — do not try to fix it yourself.
- If it fails with HTTP 429, the rate limit was hit. The client already retries up to 3 times; tell the user to wait ~10 minutes and re-run.
- Do not modify any downloaded `.original.html` files. They are the source of truth for the review phase.
