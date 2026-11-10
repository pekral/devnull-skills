# Scheduled runs

A scheduled run creates the report without you: every morning for your projects, or once a week as a summary. It reads only, asks nothing, and publishes nothing unless you name a channel.

## The prompt

| Run | Prompt |
|---|---|
| Daily, all projects | `/devnull-insights všechny projekty unattended` |
| Daily, one project | `/devnull-insights ai-olympus unattended` |
| Weekly summary | `/devnull-insights všechny projekty weekly unattended` |
| With a notification | `/devnull-insights ai-olympus unattended notify=pekral/ai-olympus#123` |

- `unattended` makes the run non-interactive. The skill asks nothing, uses saved profiles only, reads `--since-last-run`, archives the report, and remembers the run. It creates no issues, changes no profile or proposal status, and posts nothing.
- `weekly` reads the last 7 days and compares the topics with the previous weekly report in the archive.
- `notify=<owner/repo>#<issue>` is the only way a run publishes: it adds one comment with the TL;DR and the archived report path to that issue. Use an issue you created for this, for example "devnull-insights digest".

Profiles must exist before the first scheduled run: run `/devnull-insights <repository>` once interactively and confirm the profile.

## Command line

Every local scheduler runs the same command:

```bash
claude -p "/devnull-insights všechny projekty unattended" \
  --permission-mode dontAsk \
  --allowedTools "Bash(php *)" "Bash(gh repo view *)" "Bash(gh api *)" "Read" "Write"
```

- `--permission-mode dontAsk` refuses every tool that is not allowed instead of waiting for an answer, so the run cannot hang on a prompt.
- `--allowedTools` allows the skill's scripts (`php`), reading repositories with `gh`, and writing the report into the session's scratch directory. Add `"Bash(gh issue comment *)"` only when the prompt has `notify=`.
- The reports land in the archive: `<profile directory>/reports/<YYYY-MM-DD>-<name>[-weekly].md`, with the fetch data next to each one. A second report on the same day gets `-2`, `-3` … and nothing is overwritten. `php …/scripts/archive.php list` shows them.

## Linux — cron

`crontab -e` and add the line from [`examples/crontab.txt`](examples/crontab.txt). It runs on weekdays at 07:07 and writes the run log next to the profiles.

## macOS — launchd

1. Copy [`examples/cz.pekral.devnull-insights.plist`](examples/cz.pekral.devnull-insights.plist) to `~/Library/LaunchAgents/`.
2. Replace `/Users/YOU` with your home directory, and check the path of `claude` (`which claude`).
3. `launchctl load ~/Library/LaunchAgents/cz.pekral.devnull-insights.plist`

launchd runs a missed job when the Mac wakes up, which cron does not.

## Windows — Task Scheduler

Run [`examples/register-windows-task.ps1`](examples/register-windows-task.ps1) once in PowerShell. It registers a task that runs every weekday at 07:07 under your account. `Get-ScheduledTask -TaskName devnull-insights` shows it; `Unregister-ScheduledTask -TaskName devnull-insights` removes it.

## Claude Code routine (cloud)

`/schedule` creates a routine that runs in the cloud on a cron schedule, for example:

> every weekday at 7:07 run `/devnull-insights https://github.com/pekral/ai-olympus unattended`

A routine starts from a fresh checkout every time, so no local file survives between runs: no profiles, no memory, no archive. Use it either

- **stateless** — name the repository in the prompt; each run derives keywords again and cannot leave out posts reported before, or
- **with a state repository** — keep the profile directory in a private repository the routine checks out, set `DEVNULL_INSIGHTS_HOME` to that checkout, and end the prompt with "commit and push the changes in the profile directory".

The local schedulers above keep profiles, memory and archive on your machine without extra steps.
