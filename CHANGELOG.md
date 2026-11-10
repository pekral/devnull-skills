# Changelog

## [Unreleased]

### Changed
- Every output item (themes, proposals, quick wins, engagement opportunities, quotes) must link its source dev-null post as `[@author](url)`, with the URL taken verbatim from the fetched JSON; items without a source post are dropped.
- `fetch.php` runs unchanged on Windows, macOS and Linux: HTTPS through PHP streams instead of ext-curl, case-insensitive regex instead of ext-mbstring, new `--output` option instead of shell redirection (Windows PowerShell 5.1 writes UTF-16).
- The skill reads the target README via `gh api … -H "Accept: application/vnd.github.raw"` instead of the Unix-only `base64 -d`.

## [0.1.0] - 2026-11-10

### Added
- `devnull-insights` plugin and skill: scans the public dev-null.sh log for a time window, filters posts by topics and repository keywords, loads discussion replies, and proposes evidence-backed repository improvements.
- Marketplace manifests for Claude Code (`.claude-plugin/marketplace.json`) and Codex (`.agents/plugins/marketplace.json`).
