PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS items (
  id           INTEGER PRIMARY KEY,
  url          TEXT NOT NULL UNIQUE,
  source       TEXT NOT NULL,
  title        TEXT NOT NULL,
  one_liner    TEXT,
  trend_signal TEXT,
  status       TEXT NOT NULL DEFAULT 'triaged',
  relevance    TEXT,
  brief        TEXT,
  seen_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS claims (
  id              INTEGER PRIMARY KEY,
  item_id         INTEGER REFERENCES items(id),
  lesson_file     TEXT,
  category        TEXT NOT NULL,
  claim           TEXT NOT NULL,
  evidence        TEXT,
  source_url      TEXT NOT NULL,
  source_date     TEXT,
  confidence      TEXT NOT NULL,
  claim_type      TEXT NOT NULL,
  status          TEXT NOT NULL DEFAULT 'unreviewed',
  relevance_to_me TEXT,
  seen_at         TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS topics (
  id            INTEGER PRIMARY KEY,
  topic         TEXT NOT NULL,
  added_by      TEXT NOT NULL,
  status        TEXT NOT NULL DEFAULT 'pending',
  source_note   TEXT,
  lesson_file   TEXT,
  added_at      TEXT NOT NULL DEFAULT (datetime('now')),
  completed_at  TEXT
);

CREATE INDEX IF NOT EXISTS idx_items_status     ON items(status);
CREATE INDEX IF NOT EXISTS idx_items_relevance  ON items(relevance);
CREATE INDEX IF NOT EXISTS idx_claims_category  ON claims(category);
CREATE INDEX IF NOT EXISTS idx_claims_conf      ON claims(confidence);
CREATE INDEX IF NOT EXISTS idx_claims_status    ON claims(status);
CREATE INDEX IF NOT EXISTS idx_topics_status    ON topics(status);
