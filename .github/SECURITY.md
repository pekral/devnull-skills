# Security Policy

## What this repository ships

A plugin marketplace for Claude Code and Codex with one plugin, `devnull-insights`. The plugin is a skill
(`SKILL.md`), a set of templates and `topics.json`, and four PHP scripts that the agent runs on your machine.
The scripts send GET requests to the public dev-null.sh log over HTTPS, and read and write JSON and Markdown
files in the profile directory (`$DEVNULL_INSIGHTS_HOME`, `%APPDATA%\devnull-insights`,
`$XDG_CONFIG_HOME/devnull-insights` or `~/.config/devnull-insights`) and in the paths the agent passes with
`--output`. They use no API key, never sign in to dev-null.sh, and need no Composer at runtime. GitHub access
goes through your own signed-in `gh`.

That shape bounds what a vulnerability here could be. The realistic cases are:

- **Instructions in a dev-null.sh post that the agent follows.** The skill treats posts, replies and link
  previews as untrusted data. A post that makes the skill create an issue, post a comment, or edit a
  repository without your confirmation is in scope.
- **A script that reads or writes outside its directory** — for example a profile or report name that escapes
  the profile directory.
- **A script that sends data anywhere other than the dev-null.sh log.**

## Supported versions

Fixes go into the latest release. Older tags are not patched.

## Reporting a vulnerability

Report privately through GitHub's
[**Report a vulnerability**](https://github.com/pekral/devnull-skills/security/advisories/new) form, which
opens an advisory visible only to the maintainer.

Include the plugin version, the host (Claude Code or Codex) and its version, and the steps to reproduce. For a
post that steers the agent, include the post's link or its text.

**Do not open a public issue for a vulnerability.** This is a single-maintainer project, so a public report
gives every user an exploit before there is a fix.

## Out of scope

- **dev-null.sh itself.** The skill only reads its public log; a vulnerability in the site belongs to its
  operators.
- **Claude Code and Codex.** A flaw in the agent host, its permission prompts or its plugin installer belongs
  to that host's vendor.
- **What a post says.** Spam, misleading or offensive posts that the skill reports are not a vulnerability,
  unless they make the agent act as described above.
- **Development dependencies.** Composer and Pest are development tools of this repository only; the plugin
  does not load them.
