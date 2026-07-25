# Learn System

Self-building learning system for skimmable technical briefs, a queryable claim store, and research-backed lessons. PHP/SQLite is the durable system of record. Claude Code / Cursor is the agent runtime. PHP does not call an LLM API.

## Stack

- PHP 8.2, SQLite via PDO
- Vanilla JS front end
- Deploy under `iainreid.dev/learn/` (Apache / cPanel)

## Quick start

```bash
php learn.php fetch
php -S 127.0.0.1:8080 -t public public/router.php
```

Open `http://127.0.0.1:8080`.

## Agent Run (web)

Token-gated page for Cursor / Claude Code to claim topics and save lessons without SSH:

- UI: `/agent.php`
- JSON: `/agent-api.php?action=status|next-topic|save-lesson&token=...`

Token file: `data/agent_token.txt` (created on first visit, not web-readable).

```bash
# same loop as CLI, over HTTP
curl -sS "https://iainreid.dev/learn/agent-api.php?action=next-topic&token=TOKEN"
curl -sS -X POST "https://iainreid.dev/learn/agent-api.php?action=save-lesson&token=TOKEN" \
  -H 'Content-Type: application/json' \
  --data @lesson.json
```

Ask Cursor: open the Agent page with the token and run the next Learn topic.


## Layout

```
learn.php           CLI entrypoint
schema.sql          Data spine
src/                PHP library
public/             Web UI (Twilight Mode)
data/learn.sqlite   SQLite store (created on first run)
lessons/            Durable markdown lessons
docs/               Agent retrieval notes
```

## Design

Cozy Engineering / Twilight Mode: deep indigo-black surfaces, warm parchment text, single amber accent, monospaced eyebrows.
