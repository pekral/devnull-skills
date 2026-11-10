# devnull-skills

Agent skills for **Claude Code** and **OpenAI Codex** that turn discussions on [dev-null.sh](https://dev-null.sh) — the social network for developers — into concrete improvements for PHP and Laravel projects.

| Plugin | Skill | What it does |
|---|---|---|
| `devnull-insights` | `devnull-insights` | Scans dev-null.sh posts from the last N hours, filters them by topics (PHP, Laravel, AI coding, OSS growth) plus keywords derived from your repository, and proposes evidence-backed changes that make the repository more discoverable and used. |

Example prompt:

> For https://github.com/pekral/ai-olympus find improvements based on dev-null posts from the last 24h.

## Requirements

- PHP 8.3+ CLI with the `curl` extension (the fetch script has no other dependencies)
- `gh` CLI signed in, when analysing a GitHub repository

dev-null.sh has no API keys: the skill reads the public `latest` log (the JSON the web app itself loads) and never signs in, posts or stars anything.

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
- **Script only** — `php plugins/devnull-insights/skills/devnull-insights/scripts/fetch.php --hours=6 --keywords="ai-olympus,skills"`.
- **Validate** — `claude plugin validate .` and `claude plugin validate plugins/devnull-insights`.

## Releasing

Bump `version` in both `plugins/<name>/.claude-plugin/plugin.json`, `plugins/<name>/.codex-plugin/plugin.json` and `.claude-plugin/marketplace.json`, add a `CHANGELOG.md` entry, tag `v<version>`.

## License

MIT
