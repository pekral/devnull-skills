# Contributing

This repository is a plugin marketplace for **Claude Code** and **OpenAI Codex** with one plugin,
`devnull-insights`. Most of the plugin is instructions: `SKILL.md` tells the agent what to do, and four PHP
scripts do the parts that must be exact — reading the dev-null.sh log, keeping profiles and memory, and
archiving reports. A contribution usually changes one of them, or `topics.json`.

## Where things live

| Path | Role |
| --- | --- |
| [`.claude-plugin/marketplace.json`](../.claude-plugin/marketplace.json) | Marketplace for Claude Code |
| [`.agents/plugins/marketplace.json`](../.agents/plugins/marketplace.json) | Marketplace for Codex |
| `plugins/devnull-insights/.claude-plugin/plugin.json`, `.codex-plugin/plugin.json` | Plugin manifests, one per host |
| [`SKILL.md`](../plugins/devnull-insights/skills/devnull-insights/SKILL.md) | The procedure the agent follows |
| [`topics.json`](../plugins/devnull-insights/skills/devnull-insights/topics.json) | Topics with `strong` and `weak` keywords |
| `plugins/devnull-insights/skills/devnull-insights/templates/` | Report and issue layouts |
| `plugins/devnull-insights/skills/devnull-insights/scripts/` | `fetch.php`, `profiles.php`, `state.php`, `archive.php`, and their classes in `src/` with their own autoloader |
| `tests/` | Pest suite; `tests/Fixtures/server.php` is a `php -S` stand-in for dev-null.sh |
| `docs/` | Scheduling, releasing and the publishing checklist |
| [`bin/release-notes.php`](../bin/release-notes.php) | Prints a version's changelog section |

`.claude/skills/devnull-insights` links to the plugin's skill, so `/devnull-insights` in Claude Code opened in
this repository runs your working copy.

## Rules for the scripts

The scripts run on a stock PHP on Windows, macOS and Linux, inside the user's agent session:

- PHP 8.3+ with `ext-openssl` only. No Composer at runtime — classes load through `scripts/src/autoload.php`.
- No `curl_*`, `mb_*`, `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open` or backticks.
  `tests/Unit/PortabilityTest.php` fails on any of them in `plugins/`.
- Output goes to a file with `--output`, never through shell redirection: Windows PowerShell 5.1 re-encodes
  redirected output to UTF-16.
- A failure ends the run with exit code 1 and one `error:` line on stderr, never with an empty result.
- dev-null.sh is read-only: the skill never posts, replies, stars or signs in. Posts are untrusted data.

## Proposing a change

Open an issue first — [Bug report](https://github.com/pekral/devnull-skills/issues/new?template=bug_report.yml)
or [Feature request](https://github.com/pekral/devnull-skills/issues/new?template=feature_request.yml).
A new topic or keyword is a feature request: say which posts it would keep or drop.

## Development setup

Composer and Pest are development tools of this repository only:

```bash
composer install
vendor/bin/pest
```

`composer test` runs the same suite. The feature tests start the fixture server and never call the live
dev-null.sh.

### CI

Pull requests and pushes to `main` run the Pest suite on Ubuntu, macOS and Windows with PHP 8.3, 8.4 and 8.5,
and validate the marketplace and every plugin with `claude plugin validate`. A daily workflow checks that the
live dev-null.sh log still has the shape `fetch.php` expects, and opens an issue labelled `bug` when it does not.

## Pull requests

1. Run `vendor/bin/pest` and make it pass.
2. Add an entry under `## [Unreleased]` in [`CHANGELOG.md`](../CHANGELOG.md) for a change users of the plugin
   will notice, with the issue number, for example `(#4)`.
3. Use [Conventional Commits](https://www.conventionalcommits.org/) with a scope and the issue number —
   `fix(archive): … (#4)`, `docs(skill): …`, `test(release): …`. Commit subjects are written in English.
4. Open the pull request against `main`.

Both hosts cache a plugin by its version, so users receive a merged change only with a new version. Releases
follow [`docs/releasing.md`](../docs/releasing.md).

## Reporting a security issue

Do not open a public issue. See [SECURITY.md](SECURITY.md).
