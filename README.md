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

## Install

### Claude Code

```text
/plugin marketplace add pekral/devnull-skills
/plugin install devnull-insights@devnull-skills
```

### Codex

```bash
codex plugin marketplace add git@github.com:pekral/devnull-skills.git
```

Then install `devnull-insights` from the Plugins directory (`.agents/plugins/marketplace.json`).

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

Bump `version` in both `plugins/<name>/.claude-plugin/plugin.json`, `plugins/<name>/.codex-plugin/plugin.json` and `.claude-plugin/marketplace.json`, add a `CHANGELOG.md` entry, tag `v<version>`.

## License

MIT
