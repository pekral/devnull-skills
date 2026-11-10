# devnull-insights: registers a Windows Task Scheduler task that writes the daily report for all projects,
# weekdays at 07:07, under the current user. Run once in PowerShell. Remove with:
#   Unregister-ScheduledTask -TaskName devnull-insights -Confirm:$false

$claude = (Get-Command claude -ErrorAction Stop).Source
$log = Join-Path $env:APPDATA 'devnull-insights\task.log'
New-Item -ItemType Directory -Force -Path (Split-Path $log) | Out-Null

$arguments = @(
    '-p', '"/devnull-insights všechny projekty unattended"',
    '--permission-mode', 'dontAsk',
    '--allowedTools', '"Bash(php *)"', '"Bash(gh repo view *)"', '"Bash(gh api *)"', 'Read', 'Write'
) -join ' '

# cmd.exe appends the output of every run to the log file.
$action = New-ScheduledTaskAction -Execute 'cmd.exe' -Argument "/c `"`"$claude`" $arguments >> `"$log`" 2>&1`""
$trigger = New-ScheduledTaskTrigger -Weekly -DaysOfWeek Monday, Tuesday, Wednesday, Thursday, Friday -At '07:07'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask -TaskName 'devnull-insights' -Action $action -Trigger $trigger -Settings $settings `
    -Description 'Daily /dev/null insights report for all projects' | Out-Null

Write-Host "Registered task devnull-insights; log: $log"
