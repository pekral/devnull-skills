# Turn dev-null.sh discussions into improvements for your PHP and Laravel projects

[![MIT Licensed](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
![CI](https://github.com/pekral/devnull-skills/workflows/CI/badge.svg)

A plugin marketplace for **Claude Code** and **OpenAI Codex**. Its skill `devnull-insights` reads recent posts on [dev-null.sh](https://dev-null.sh), the social network for developers, and keeps those that match your topics and your repository's keywords. From them it proposes concrete changes to the repository, and every proposal links the posts it rests on.

dev-null.sh has no API keys. The skill reads the public `latest` log, the same JSON the web app loads. It never signs in, posts or stars anything.

## Requirements

- Claude Code or Codex
- PHP 8.3+ CLI with `ext-openssl`, which Composer already requires. The scripts need no other extension and no Unix shell tools, so they run the same on Windows, macOS and Linux.
- `gh` signed in, when the skill analyses a GitHub repository or creates issues

## Installation

### Claude Code

```text
/plugin marketplace add pekral/devnull-skills
/plugin install devnull-insights@devnull-skills
```

Update with `claude plugin marketplace update devnull-skills`.

### Codex

```bash
codex plugin marketplace add pekral/devnull-skills
codex plugin add devnull-insights@devnull-skills
```

Update with `codex plugin marketplace upgrade devnull-skills`.

Both tools cache a plugin by its version, so an update arrives only with a new version.

## Configuration

Nothing is required. Two things can be changed:

- **Topics** — `plugins/devnull-insights/skills/devnull-insights/topics.json` lists what the skill looks for. Each topic has `strong` keywords, which keep a post on their own, and `weak` keywords, which only add to the score of a post a strong keyword keeps:

  ```json
  {
      "laravel": {
          "strong": ["laravel", "livewire", "filament"],
          "weak": ["forge", "sail", "horizon"]
      }
  }
  ```

- **Where profiles, memory and the report archive live** — `$DEVNULL_INSIGHTS_HOME`, else `%APPDATA%\devnull-insights` on Windows, else `$XDG_CONFIG_HOME/devnull-insights`, else `~/.config/devnull-insights`. Plugin updates never touch this directory.

## Quick start

Ask for a repository and a window:

```text
/devnull-insights for https://github.com/pekral/ai-olympus find improvements from the posts of the last 24h
```

The report covers the themes people discuss, ranked proposals for the repository, quick wins for today and posts worth replying to. Every item links its dev-null post. At the end, the skill offers to save a **profile** with the repository's keywords. After you confirm, later runs need only the name:

```text
/devnull-insights ai-olympus
/devnull-insights all projects
```

With a profile, a run shows only posts it has not reported before. A proposal keeps its ID (`P1`, `P2` …) across runs. Answer in plain words, for example "P2 is not for us", "P1 is done", or "create issues for P1 and P3". The skill creates an issue only after you confirm its preview.

## What it does

- **Filters noise** — drops posts that hit only weak keywords, merges series of near-identical posts by one author, and reports what it filtered out.
- **Remembers each project** — reported posts, the last run and every proposal with its status, in plain JSON you can edit.
- **Turns proposals into issues** — after a duplicate search and your confirmation, with the source links in the issue body.
- **Runs on a schedule** — `unattended` asks nothing and publishes nothing; `weekly` compares the topics with the previous week; reports are archived under their date. See [docs/scheduling.md](docs/scheduling.md) for cron, launchd, Task Scheduler and Claude Code routines.

The skill calls four PHP scripts in `plugins/devnull-insights/skills/devnull-insights/scripts/`. Each one documents its options at the top of the file:

| Script | Purpose |
|---|---|
| `fetch.php` | Reads the log for a window and selects the matching posts, for keywords or saved profiles |
| `profiles.php` | Lists, shows, saves and removes project profiles |
| `state.php` | Shows a project's memory, records a run, adds and decides proposals |
| `archive.php` | Saves, lists and finds archived reports |

## Documentation

- [`SKILL.md`](plugins/devnull-insights/skills/devnull-insights/SKILL.md) — the procedure the agent follows
- [`templates/report.md`](plugins/devnull-insights/skills/devnull-insights/templates/report.md) and [`templates/issue.md`](plugins/devnull-insights/skills/devnull-insights/templates/issue.md) — the report and issue layouts
- [`docs/scheduling.md`](docs/scheduling.md) — scheduled runs

## Testing

```bash
composer install
vendor/bin/pest
```

Composer and Pest are development tools of this repository only. The feature tests run the scripts against `tests/Fixtures/server.php`, a `php -S` stand-in for dev-null.sh, with the ext-curl and ext-mbstring functions disabled. CI runs the suite on Ubuntu, macOS and Windows with PHP 8.3, 8.4 and 8.5. A daily workflow checks that the live dev-null.sh log still has the shape the scripts expect.

To work on the skill, open Claude Code in this repository. `.claude/skills/devnull-insights` links to the plugin's skill, so `/devnull-insights` runs your working copy.

## Releasing

1. Set the new `version` in `.claude-plugin/marketplace.json`, `plugins/<name>/.claude-plugin/plugin.json` and `plugins/<name>/.codex-plugin/plugin.json`.
2. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [<version>] - <YYYY-MM-DD>` and open a new empty `## [Unreleased]` above it.
3. Run `vendor/bin/pest`, which fails when the versions differ or the changelog has no section for the version, then merge to `main`.
4. Tag and publish:

```bash
git tag v<version> && git push origin v<version>
php bin/release-notes.php <version> | gh release create v<version> --title "v<version>" --notes-file -
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).
