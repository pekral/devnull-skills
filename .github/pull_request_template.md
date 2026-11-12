## What does this change?

<!-- One or two sentences. Link the issue it resolves: Closes #123 -->

## How to test

<!-- Steps a reviewer can follow, for example a /devnull-insights request or a script command with --output. -->

## Checklist

- [ ] `vendor/bin/pest` passes.
- [ ] Changed scripts use PHP 8.3+ with `ext-openssl` only — no Composer at runtime, no `curl_*`, `mb_*` or shell functions.
- [ ] A change users of the plugin will notice has an entry under `## [Unreleased]` in `CHANGELOG.md`.
- [ ] The commit subject follows Conventional Commits and is written in English.
