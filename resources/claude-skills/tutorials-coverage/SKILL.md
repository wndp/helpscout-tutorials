---
name: tutorials-coverage
description: Identify user-facing app features that have no HelpScout tutorial, and propose what to write.
---

You are auditing tutorial coverage. The goal is to find user-facing surfaces of this app that have no corresponding HelpScout article, and to deliver a prioritized list the team can act on.

This is a **read-only research command.** Do not write code, modify the app, draft new tutorial bodies, or call the HelpScout API. Output is a single markdown report on disk plus a short chat summary.

This command assumes a Laravel + Inertia (Vue) app. The paths in step 2 are concrete; only the app-specific identifiers — your primary layout/nav component, permission props, and any custom route prefixes — need a quick confirm against this codebase before you rely on them.

## Prerequisites

- The storage directory is `storage/helpscout-tutorials/` unless `HELPSCOUT_TUTORIALS_PATH` overrides it (check with `php artisan config:show helpscout-tutorials`). Paths below are relative to it.
- `manifest.json` must exist so we know what tutorials currently exist. If it does not, tell the user to run `/tutorials-download` first and stop.

## Steps

1. Read `manifest.json`. Build the list of published article names + IDs + slugs. (Treat `status=notpublished` articles as *not covering* their topic — they are effectively retired.)
2. Discover the app's user-facing surfaces. Use an Explore subagent for breadth; ask it to enumerate, with file paths as evidence:
   - Every top-nav item in the app's primary layout (typically `resources/js/Layouts/AppLayout.vue` — confirm the actual filename), excluding items gated on an admin-only condition (e.g. a `canManage*`/`isAdmin` prop).
   - Every top-level page under `resources/js/Pages/` that a non-admin user can reach. Skip the `Admin/`, `Api/`, `Public/`, and `Auth/` subtrees (login, password reset, 2FA challenge — though 2FA *setup* is worth noting).
   - Every named route in `routes/web.php` that an end user can hit. Skip routes prefixed `api/`, `admin/`, `webhooks/`, and any signed-only routes.
   - Major UI affordances inside each top-level page: tab components, prominent buttons (Subscribe, Share, Export), per-page modals, and permission-gated features (e.g. an `export*` prop passed from the controller).
   - Distinct workflows that span multiple pages but are coherent units (e.g. "invite a team member", "change account password").
3. For each surface, decide one of:
   - **Covered** — at least one published tutorial clearly walks through this surface. Identify which one(s) by article name.
   - **Mentioned in passing** — a tutorial references the surface but doesn't walk through it. Note which tutorial mentions it.
   - **Uncovered** — no tutorial addresses this surface at all.
   - **Probably does not need a tutorial** — internal tooling, developer features, one-time transactional flows, or features the user will never see directly. Briefly justify.
4. Group the **Uncovered** + **Mentioned in passing** items into three priority tiers. Use these rules of thumb:
   - **High** — top-nav items, or features a brand-new user is likely to encounter in their first session.
   - **Medium** — features that exist but require a user to seek them out (settings, team management, exports).
   - **Low** — security niceties, advanced affordances, or things power users figure out on their own.
5. Write the report to `coverage-report.md` in the storage directory with this structure:

   ```markdown
   # Tutorial coverage report

   Generated: {iso-timestamp}
   Tutorials reviewed: {N published} ({M unpublished excluded})

   ## Coverage summary
   - Covered surfaces: {count}
   - Mentioned in passing: {count}
   - Uncovered: {count}
   - Out of scope (no tutorial needed): {count}

   ## High-priority gaps
   For each item: surface name, file/route evidence, suggested article title, one-sentence rationale.

   ## Medium-priority gaps
   Same shape.

   ## Lower-priority gaps
   Same shape.

   ## Surfaces probably not needing a tutorial
   Bulleted list with one-line rationale each.

   ## Covered surfaces (for reference)
   Bulleted list of {surface → tutorial article name}.
   ```
6. After writing the file, print a short chat summary:
   - Counts per tier
   - The top 3 high-priority gaps inline
   - The path to the full report

## Guardrails

- **Be conservative.** If a feature is plausibly covered by an existing tutorial, mark it covered and move on. False positives (claiming a gap that does not exist) waste the team's time more than false negatives.
- **Do not draft new tutorial bodies.** Suggest titles only. If the user wants a draft of a specific article, they will ask for it explicitly in a separate turn.
- **Do not call the HelpScout API.** This audit is local-only.
- **Do not modify any other file**, including the manifest, the `.original.html` files, or any code in this app.
- If you find no gaps at all, still write the report (it's a useful artifact); say so plainly in the chat summary.
