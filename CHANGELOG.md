# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-10-25

### Added

- `/tutorials-review` accepts a feature, e.g. `/tutorials-review team invitations`. It updates the article covering that feature, or drafts a new one if none exists, then offers to post it to HelpScout.
- `/tutorials-publish` accepts an article or file name to post just that item.

### Changed

- The Claude Code templates are now skills that stay in the package. `tutorials:install` symlinks them into `.claude/skills/` with relative links instead of copying them into `.claude/commands/`, so they update with the package.
- Consolidated the seven Claude Code templates into four skills. `/tutorials-create` is now part of `/tutorials-publish`, and `/tutorials-categorize` is now part of `/tutorials-categories`. `/tutorials-download` is removed because the other skills download the articles themselves when needed.
- Licensed under MIT.
- Allow PHPUnit 13; test against PHP 8.5.

### Fixed

- `/tutorials-review` and `/tutorials-coverage` now respect a custom `HELPSCOUT_TUTORIALS_PATH` instead of assuming `storage/helpscout-tutorials/`.

## [0.2.0] - 2026-06-12

### Added

- `tutorials:categories`, `tutorials:category`, and `tutorials:categorize` commands for listing, creating, updating, deleting, and assigning categories.
- `/tutorials-categories` and `/tutorials-categorize` Claude Code slash-command templates.

### Changed

- **BREAKING:** Renamed the installer command from `helpscout-tutorials:install` to `tutorials:install` so all commands share the `tutorials:*` namespace. Update any scripts or deploy steps that call `helpscout-tutorials:install`. The `vendor:publish` tags (`helpscout-tutorials-config`, `helpscout-tutorials-claude-commands`) are unchanged.
