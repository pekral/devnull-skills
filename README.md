# devnull-skills

Agent skills for **Claude Code** and **OpenAI Codex** that turn discussions on [dev-null.sh](https://dev-null.sh) — the social network for developers — into concrete improvements for PHP and Laravel projects.

| Plugin | Skill | What it does |
|---|---|---|
| `devnull-insights` | `devnull-insights` | Scans dev-null.sh posts from the last N hours, filters them by topics (PHP, Laravel, AI coding, OSS growth) plus keywords derived from your repository, and proposes evidence-backed changes that make the repository more discoverable and used. |

Example prompt:

> For https://github.com/pekral/ai-olympus find improvements based on dev-null posts from the last 24h.

## Requirements

- PHP 8.3+ CLI with `ext-openssl` (already required by Composer) — no other extensions, no shell tools; works the same on Windows, macOS and Linux
- `gh` CLI signed in, when analysing a GitHub repository

dev-null.sh has no API keys: the skill reads the public `latest` log (the JSON the web app itself loads) and never signs in, posts or stars anything.

## Topics

`plugins/devnull-insights/skills/devnull-insights/topics.json` defines what the skill looks for:

```json
{
    "laravel": {
        "strong": ["laravel", "livewire", "filament"],
        "weak": ["forge", "sail", "horizon"]
    }
}
```

- A **strong** keyword keeps a post on its own when the author wrote it.
- A **weak** keyword is too common for that: it only adds to the score of a post a strong keyword keeps. A strong keyword found only in a link preview or a quoted post counts as weak.
- A plain list (`"php": ["php", "composer"]`) means all keywords are strong.
- Several near-identical posts of one author (same first three words) are merged into the most engaging one.

## Project profiles

A profile saves a repository with its keywords, so `/devnull-insights ai-olympus` or `/devnull-insights všechny projekty` runs without questions. The skill proposes a profile after the first report for a new repository and saves it when you confirm. You can also manage profiles yourself:

```bash
php plugins/devnull-insights/skills/devnull-insights/scripts/profiles.php list
php plugins/devnull-insights/skills/devnull-insights/scripts/profiles.php save --name=ai-olympus \
    --repository=https://github.com/pekral/ai-olympus --keywords="ai-olympus,claude code,subagents" \
    --weak-keywords="skills,rules" --exclude-keywords="hiring" --topics=php,laravel,ai-coding --hours=24
php plugins/devnull-insights/skills/devnull-insights/scripts/profiles.php remove --name=ai-olympus
```

Profiles live in `projects.json` outside the plugin, so updates never overwrite them: `$DEVNULL_INSIGHTS_HOME`, else `%APPDATA%\devnull-insights` on Windows, else `$XDG_CONFIG_HOME/devnull-insights`, else `~/.config/devnull-insights`. `fetch.php --profile=all` reads the dev-null.sh log once and selects the posts of every profile from it.

## Memory between runs

With a profile the skill remembers each project in `state/<profile>.json` next to `projects.json`:

- **Reported posts** are left out of the next run (`filtered_out.already_seen`), and `--since-last-run` reads only the time since the last report. Posts are forgotten after 30 days.
- **Proposals** keep a stable ID (`P1`, `P2`…) and a status: `new`, `accepted`, `done` or `rejected`. A new post that supports an earlier proposal reinforces it instead of creating a duplicate. A rejected proposal does not come back unless a clearly stronger signal appears.
- Tell the skill your decision in plain words ("P2 zamítni", "P1 je hotové"), or use the script:

```bash
php plugins/devnull-insights/skills/devnull-insights/scripts/state.php show --profile=ai-olympus
php plugins/devnull-insights/skills/devnull-insights/scripts/state.php proposal-set --profile=ai-olympus --id=P2 --status=rejected --note="Out of scope"
```

The state file is plain JSON and can be edited by hand.

## From proposal to issue

After a report, ask for issues by proposal ID — "založ issues pro P1 a P3". The skill:

1. skips proposals that already have an issue and searches the repository for similar titles,
2. drafts each issue from `templates/issue.md` with the dev-null source links,
3. shows the preview and creates nothing until you confirm,
4. creates the issues with `gh issue create --body-file` (optional label `from-devnull`),
5. stores each issue link on its proposal, so the next report knows it is being worked on.

## Scheduled runs

`/devnull-insights všechny projekty unattended` runs without questions: saved profiles only, nothing published, the report archived under its date. Add `weekly` for a 7-day summary compared with the previous week, and `notify=<owner/repo>#<issue>` to get the TL;DR as a comment on an issue. [`docs/scheduling.md`](docs/scheduling.md) shows how to run it every morning with cron (Linux), launchd (macOS), Task Scheduler (Windows), or a Claude Code routine, with ready-made files in [`docs/examples`](docs/examples).

```bash
php plugins/devnull-insights/skills/devnull-insights/scripts/archive.php list
```

## Install

While the repository is private, both tools clone it with your own Git credentials (`gh auth login`, or an SSH key on GitHub).

### Claude Code

```text
/plugin marketplace add pekral/devnull-skills
/plugin install devnull-insights@devnull-skills
```

The same works from a terminal: `claude plugin marketplace add pekral/devnull-skills` and `claude plugin install devnull-insights@devnull-skills`. Update with `claude plugin marketplace update devnull-skills`.

### Codex

```bash
codex plugin marketplace add git@github.com:pekral/devnull-skills.git
codex plugin add devnull-insights@devnull-skills
```

Codex reads `.agents/plugins/marketplace.json`. Update with `codex plugin marketplace upgrade devnull-skills`.

Both tools install a plugin into a cache keyed by its version (`…/devnull-insights/<version>/`), so users get a change only after the version goes up — see [Releasing](#releasing).

## Local development

This repository is its own test bed:

- **Fastest loop** — `.claude/skills/devnull-insights` is a symlink to the plugin's skill, so Claude Code opened in this directory loads your working copy as a project skill. Edit, then start a new session (or `/reload-plugins`) and run `/devnull-insights`.
- **Marketplace flow** — `/plugin marketplace add ./` then `/plugin install devnull-insights@devnull-skills` installs from the local checkout exactly as users will. Avoid having both active at once, or the skill appears twice.
- **Codex** — `codex plugin marketplace add ./`.
- **Script only** — `php plugins/devnull-insights/skills/devnull-insights/scripts/fetch.php --hours=6 --keywords="ai-olympus,skills" --output=devnull.json`.
- **Validate** — `claude plugin validate .` and `claude plugin validate plugins/devnull-insights`.

### Tests

```bash
composer install
vendor/bin/pest
```

Composer and Pest are development tools of this repository only; the plugins run on a plain PHP CLI.

- `tests/Feature` runs `fetch.php` as the skill does, against `tests/Fixtures/server.php` — a `php -S` stand-in for dev-null.sh with paging, the Inertia version handshake, 429/500 responses and a changed response shape. Every run disables the ext-curl and ext-mbstring functions, so a call to either fails the test.
- `tests/Unit` covers topic matching, the manifests, and a token scan that keeps ext-curl, ext-mbstring and shell calls out of the plugin scripts.
- **CI** (`.github/workflows/ci.yml`) runs the tests on Ubuntu, macOS and Windows with PHP 8.3, 8.4 and 8.5, and validates the marketplace and plugin manifests.
- **Contract smoke test** (`.github/workflows/contract-smoke.yml`) reads the live dev-null.sh log once a day and opens an issue when the response no longer has the shape `fetch.php` relies on.

## Releasing

1. Set the new `version` in `.claude-plugin/marketplace.json`, `plugins/<name>/.claude-plugin/plugin.json` and `plugins/<name>/.codex-plugin/plugin.json`. `ManifestsTest` fails while they differ.
2. In `CHANGELOG.md`, rename `## [Unreleased]` to `## [<version>] - <YYYY-MM-DD>` and open a new empty `## [Unreleased]` above it. `ManifestsTest` fails when the version has no section.
3. Run `vendor/bin/pest`, then commit and merge to `main`.
4. Tag the release and publish it with the changelog section as notes:

```bash
git tag v<version> && git push origin v<version>
php bin/release-notes.php <version> | gh release create v<version> --title "v<version>" --notes-file -
```

Before the repository goes public, work through [`docs/publishing-checklist.md`](docs/publishing-checklist.md).

## License

MIT
