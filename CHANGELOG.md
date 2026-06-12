# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `tutorials:categories`, `tutorials:category`, and `tutorials:categorize` commands for listing, creating, updating, deleting, and assigning categories.
- `/tutorials-categories` and `/tutorials-categorize` Claude Code slash-command templates.

### Changed

- **BREAKING:** Renamed the installer command from `helpscout-tutorials:install` to `tutorials:install` so all commands share the `tutorials:*` namespace. Update any scripts or deploy steps that call `helpscout-tutorials:install`. The `vendor:publish` tags (`helpscout-tutorials-config`, `helpscout-tutorials-claude-commands`) are unchanged.
