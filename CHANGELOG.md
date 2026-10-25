# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **BREAKING:** The Claude Code templates are now skills that stay in the package. `tutorials:install` symlinks them into `.claude/skills/` with relative links instead of copying them into `.claude/commands/`, so they update with the package. The slash-command names are unchanged. Re-run `php artisan tutorials:install`; it offers to remove the old copies from `.claude/commands/`. The `helpscout-tutorials-claude-commands` publish tag has been removed.
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
