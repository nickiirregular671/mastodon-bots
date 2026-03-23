-- Track remote quote posts (parallel to likes/boosts)
CREATE TABLE IF NOT EXISTS quotes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id     INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    actor_uri   TEXT    NOT NULL,
    activity_id TEXT    NOT NULL DEFAULT '',
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(post_id, actor_uri)
);

CREATE INDEX IF NOT EXISTS idx_quotes_post_id ON quotes(post_id);
