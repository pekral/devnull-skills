# Releasing

Both Claude Code and Codex cache a plugin by its version, so users receive a change only with a new version.

1. Set the new `version` in `.claude-plugin/marketplace.json`, `plugins/<name>/.claude-plugin/plugin.json` and `plugins/<name>/.codex-plugin/plugin.json`.
2. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [<version>] - <YYYY-MM-DD>` and open a new empty `## [Unreleased]` above it.
3. Run `vendor/bin/pest`, which fails when the versions differ or the changelog has no section for the version, then merge to `main`.
4. Tag and publish:

```bash
git tag v<version> && git push origin v<version>
php bin/release-notes.php <version> | gh release create v<version> --title "v<version>" --notes-file -
```

`bin/release-notes.php` prints the changelog section of the version and exits with code 1 when the section is missing or empty.
