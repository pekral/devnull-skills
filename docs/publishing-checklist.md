# Publishing checklist

Work through this list before the repository changes from private to public. Every item is a check you can do in a few minutes.

## Content

- [ ] `LICENSE` is present and matches the `license` field of every `plugin.json` (MIT).
- [ ] `README.md` is in English and every command in it was run on the current version.
- [ ] No personal data in the repository: no e-mail addresses, tokens, private repository names, or local paths other than placeholders like `/Users/YOU`. Check with `git grep -nE "@[a-z0-9-]+\.[a-z]{2,}|ghp_|github_pat_|/Users/[a-z]"` and review every hit.
- [ ] Test fixtures are synthetic: `tests/Fixtures/server.php` holds no real dev-null.sh posts.
- [ ] A sample report exists in the README or in `docs/`, built from a run whose cited posts are public and quoted briefly.
- [ ] The git history holds no secret: `git log -p | grep -nE "ghp_|github_pat_|BEGIN .*PRIVATE KEY"` returns nothing.

## Quality

- [ ] The CI workflow ran on `main` and is green on Ubuntu, macOS and Windows. While GitHub Actions billing blocks the jobs (issue #6), this item stays open.
- [ ] The contract smoke test ran once by hand (*Actions → dev-null.sh contract smoke test → Run workflow*) and passed.
- [ ] `claude plugin validate .` and `claude plugin validate plugins/devnull-insights` pass.

## Install

- [ ] A clean Claude Code configuration installs the plugin from GitHub. Set `CLAUDE_CONFIG_DIR` to a new empty directory, then run `claude plugin marketplace add pekral/devnull-skills` and `claude plugin install devnull-insights@devnull-skills`.
- [ ] A clean Codex configuration installs it. Set `CODEX_HOME` to a new empty directory, then run `codex plugin marketplace add pekral/devnull-skills` and `codex plugin add devnull-insights@devnull-skills`.
- [ ] After the switch to public, both commands work without Git credentials. Claude Code verified on 2026-11-12 with Git credentials disabled; Codex not yet verified.

## Release

- [x] A version is released as described in [`releasing.md`](releasing.md): manifests at one version, a changelog section for it, tag `v<version>`, and a GitHub release with `bin/release-notes.php` as notes.
- [ ] The repository description and topics on GitHub describe the plugin (for example `claude-code`, `codex`, `agent-skills`, `php`, `laravel`).
