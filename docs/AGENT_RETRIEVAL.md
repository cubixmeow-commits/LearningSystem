# Agent retrieval layer

Before advising from this store, call the CLI grounding queries below. PHP is the system of record. Claude Code / Cursor is the research runtime.

## Grounding queries

```bash
# Store-as-lens for a topic (category + text match)
php learn.php store-lens "sqlite wal"

# By category
php learn.php claims-query --category dx
php learn.php claims-query --category architecture --confidence high

# By confidence
php learn.php claims-query --confidence high

# By review status
php learn.php claims-query --status confirmed
php learn.php claims-query --status unreviewed

# By relevance_to_me (project name or general)
php learn.php claims-query --relevance general
```

## Trust rules

- High-stakes planning: read `status=confirmed` only, prefer `confidence=high`.
- Broad synthesis: may read all statuses and confidences.
- Never present a `low` confidence claim or a `claim_type=synthesis` claim as settled fact.
- Every claim must keep `source_url`. If provenance is missing, do not store it.

## Run loop

```bash
php learn.php fetch
php learn.php next-items          # triage JSON in, then save-triage
php learn.php next-brief          # research, then save-brief
php learn.php add-repo <url>      # manual intake, skips triage
php learn.php add-topic "..." --note "why"
php learn.php next-topic          # claims pending/in-progress + store lens
php learn.php save-lesson in.json # writes lessons/*.md + claims + optional gap topics
```

## Claim identity

Two claims match when `source_url` + normalized claim text + `item_id`/`lesson_file` all match. Normalization: trim, collapse whitespace, lowercase. Matches update; they do not insert duplicates.
