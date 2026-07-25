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

## CLI

See `docs/AGENT_RETRIEVAL.md` for grounding queries and the full agent run loop.

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
