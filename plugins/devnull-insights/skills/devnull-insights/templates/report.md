<!--
Report template for devnull-insights. Rules:
- Translate every heading and label into the user's language; keep the emoji, order and numbering.
- Keep the sections in this order. Drop a section marked (optional) when it has nothing to show — never leave an empty
  section or a placeholder. Never add sections that are not here.
- Mode decides which sections appear:
    repository  — the user names a repository/project       → 0, 1, 2, 3, 4, 5, (6), 7
    digest      — no repository, "what is happening"         → 0, 1, 2, 5, 6, 7
    question    — a specific question ("what do people…")    → 0, ❓ Answer, 2, 5, (6), 7
- Every row with a post carries its source link `[@author](url)` (see Source links in SKILL.md).
- Table cells stay short (≤ 12 words); detail goes under the table, never inside it.
- Strength: ●●● = 3+ independent authors or strong engagement, ●●○ = 2 authors or one well-discussed post, ●○○ = one post.
-->

# 📡 /dev/null insights — {subject}

| | |
|---|---|
| **Query** | {the user's request in one line} |
| **Window** | {from} → {to} UTC ({hours} h){, ⚠️ incomplete — stopped at page limit} |
| **Posts** | {scanned} scanned · {matched} matched · {relevant} relevant after triage |
| **Topics** | {topic list}{ + repository keywords: kw1, kw2, …} |

## 0. 🧭 TL;DR

- {the single most important finding, with its source link}
- {second finding}
- {what to do first — in repository mode the top proposal; otherwise the strongest theme}

## ❓ Answer
<!-- question mode only: a direct answer to the user's question in 3–8 sentences, every claim linked to its post. -->

## 1. 🎯 Repository snapshot
<!-- repository mode only -->

| | |
|---|---|
| **Repository** | [{owner/repo}]({url}) — {one-line purpose} |
| **Traction** | ★ {stars} · forks {forks} · last release {tag} ({date}) |
| **Strengths** | {what is already good, 1–3 items} |
| **Gaps** | {missing topics, docs, demo, …, 1–4 items} |

## 2. 🔥 What people talk about

| # | Theme | Signal | Strength | Sources |
|---|---|---|---|---|
| T1 | {short theme name} | {pain point / wish / trend / competitor / distribution} | ●●○ | [@author](url), [@author](url) |

**T1 — {theme name}.** {one or two sentences on the need, with the strongest reply if it adds something}

## 3. 💡 Proposals for {repo}
<!-- repository mode only; ranked, 3–7 items -->

| # | Proposal | Themes | Impact | Effort | Risk |
|---|---|---|---|---|---|
| P1 | {imperative, ≤ 8 words} | T1, T3 | high | S | low |

### P1 — {proposal title}
- **Change:** {concrete files, sections, commands, features}
- **Why:** {the signal} — [@author](url)
- **Already in the repository:** {what exists, with `path`, or "nothing"}
- **Effect:** {expected effect on discoverability / usage}
- **Effort / risk:** {S|M|L} / {low|medium|high} — {one-line reason}

## 4. ⚡ Quick wins today
<!-- repository mode only; max 3, each under an hour, each derived from a proposal -->

- [ ] **{action}** — {where exactly} · from P{n} · [@author](url)

## 5. 💬 Where to join the conversation

| Post | About | What you could add |
|---|---|---|
| [@author](url) | {topic, ≤ 8 words} | {your angle, ≤ 12 words} |

## 6. 📈 Trending outside the topics (optional)
<!-- up to 5 posts from trending_overall that matched no topic but drew attention; skip when nothing stands out -->

- [@author](url) — {one line} (★ {stars} · 💬 {replies})

## 7. ℹ️ Method and limits

- **Source:** public `latest` log of dev-null.sh, read-only; nothing was posted, starred or replied.
- **Filtered out:** {filtered_out.total} posts by the script ({weak_only} weak-only, {series_merged} merged into series, {below_min_score} below the score) and {n} more in triage ({main reasons}).
- **Not verified:** {anything assumed but not checked, or "nothing"}.
- **Re-run:** `/devnull-insights {same request}` — {suggested window or keyword change, if any}.
