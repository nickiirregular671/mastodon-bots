PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;
PRAGMA synchronous = NORMAL;

-- Global settings
CREATE TABLE IF NOT EXISTS settings (
    key   TEXT PRIMARY KEY NOT NULL,
    value TEXT NOT NULL DEFAULT ''
);

-- Bot accounts
CREATE TABLE IF NOT EXISTS accounts (
    id                          INTEGER PRIMARY KEY AUTOINCREMENT,
    username                    TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    display_name                TEXT    NOT NULL DEFAULT '',
    bio                         TEXT    NOT NULL DEFAULT '',
    avatar_path                 TEXT    NOT NULL DEFAULT '',
    header_path                 TEXT    NOT NULL DEFAULT '',
    public_key                  TEXT    NOT NULL DEFAULT '',
    private_key                 TEXT    NOT NULL DEFAULT '',
    password_hash               TEXT    NOT NULL DEFAULT '',
    created_at                  TEXT    NOT NULL DEFAULT (datetime('now')),
    moved_to                    TEXT    NOT NULL DEFAULT '',
    also_known_as               TEXT    NOT NULL DEFAULT '[]',
    manually_approves_followers INTEGER NOT NULL DEFAULT 0,
    locked                      INTEGER NOT NULL DEFAULT 0,
    discoverable                INTEGER NOT NULL DEFAULT 1,
    indexable                   INTEGER NOT NULL DEFAULT 1,
    noindex                     INTEGER NOT NULL DEFAULT 0,
    profile_fields              TEXT    NOT NULL DEFAULT '[]',
    featured_hashtags           TEXT    NOT NULL DEFAULT '[]',
    fediverse_creator           TEXT    NOT NULL DEFAULT ''
);

-- Posts
CREATE TABLE IF NOT EXISTS posts (
    id                      INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id              INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    activity_id             TEXT    NOT NULL UNIQUE,
    content_raw             TEXT    NOT NULL DEFAULT '',
    content_html            TEXT    NOT NULL DEFAULT '',
    content_warning         TEXT    NOT NULL DEFAULT '',
    visibility              TEXT    NOT NULL DEFAULT 'public'
                                    CHECK(visibility IN ('public','unlisted','private','direct')),
    in_reply_to_id          TEXT    NOT NULL DEFAULT '',
    in_reply_to_account_uri TEXT    NOT NULL DEFAULT '',
    quote_url               TEXT    NOT NULL DEFAULT '',
    language                TEXT    NOT NULL DEFAULT 'en',
    local_only              INTEGER NOT NULL DEFAULT 0,
    published_at            TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at              TEXT,
    deleted_at              TEXT,
    replies_count           INTEGER NOT NULL DEFAULT 0,
    likes_count             INTEGER NOT NULL DEFAULT 0,
    boosts_count            INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_posts_account_id   ON posts(account_id);
CREATE INDEX IF NOT EXISTS idx_posts_published_at ON posts(published_at DESC);
CREATE INDEX IF NOT EXISTS idx_posts_deleted_at   ON posts(deleted_at);
CREATE INDEX IF NOT EXISTS idx_posts_activity_id  ON posts(activity_id);

-- Media attachments
CREATE TABLE IF NOT EXISTS media_attachments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER REFERENCES posts(id) ON DELETE SET NULL,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    file_path  TEXT    NOT NULL,
    file_url   TEXT    NOT NULL,
    mime_type  TEXT    NOT NULL,
    alt_text   TEXT    NOT NULL DEFAULT '',
    blurhash   TEXT    NOT NULL DEFAULT '',
    width      INTEGER NOT NULL DEFAULT 0,
    height     INTEGER NOT NULL DEFAULT 0,
    duration   REAL    NOT NULL DEFAULT 0,
    file_size  INTEGER NOT NULL DEFAULT 0,
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_media_post_id    ON media_attachments(post_id);
CREATE INDEX IF NOT EXISTS idx_media_account_id ON media_attachments(account_id);

-- Remote actors following local bots
CREATE TABLE IF NOT EXISTS followers (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id     INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    follower_uri   TEXT    NOT NULL,
    follower_inbox TEXT    NOT NULL,
    shared_inbox   TEXT    NOT NULL DEFAULT '',
    accepted       INTEGER NOT NULL DEFAULT 0,
    follow_activity_id TEXT NOT NULL DEFAULT '',
    created_at     TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(account_id, follower_uri)
);

CREATE INDEX IF NOT EXISTS idx_followers_account_id ON followers(account_id);

-- Local bots following remote actors
CREATE TABLE IF NOT EXISTS following (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id         INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    following_uri      TEXT    NOT NULL,
    following_inbox    TEXT    NOT NULL,
    shared_inbox       TEXT    NOT NULL DEFAULT '',
    accepted           INTEGER NOT NULL DEFAULT 0,
    follow_activity_id TEXT    NOT NULL DEFAULT '',
    created_at         TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(account_id, following_uri)
);

CREATE INDEX IF NOT EXISTS idx_following_account_id ON following(account_id);

-- Blocked actors
CREATE TABLE IF NOT EXISTS blocks (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    target_uri TEXT    NOT NULL,
    created_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(account_id, target_uri)
);

-- Likes received
CREATE TABLE IF NOT EXISTS likes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    actor_uri  TEXT    NOT NULL,
    created_at TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(post_id, actor_uri)
);

-- Boosts/Announces received
CREATE TABLE IF NOT EXISTS boosts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id     INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    actor_uri   TEXT    NOT NULL,
    activity_id TEXT    NOT NULL,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE(post_id, actor_uri)
);

-- Activity log (all sent + received)
CREATE TABLE IF NOT EXISTS activities_log (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    account_id    INTEGER REFERENCES accounts(id) ON DELETE SET NULL,
    direction     TEXT    NOT NULL CHECK(direction IN ('in','out')),
    activity_type TEXT    NOT NULL,
    activity_json TEXT    NOT NULL DEFAULT '{}',
    remote_actor  TEXT    NOT NULL DEFAULT '',
    target_inbox  TEXT    NOT NULL DEFAULT '',
    status        TEXT    NOT NULL DEFAULT 'pending'
                          CHECK(status IN ('pending','delivered','failed','received')),
    error         TEXT    NOT NULL DEFAULT '',
    created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    delivered_at  TEXT
);

CREATE INDEX IF NOT EXISTS idx_log_account_id ON activities_log(account_id);
CREATE INDEX IF NOT EXISTS idx_log_created_at ON activities_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_log_direction  ON activities_log(direction);

-- Hashtags
CREATE TABLE IF NOT EXISTS hashtags (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    tag     TEXT    NOT NULL COLLATE NOCASE
);

CREATE INDEX IF NOT EXISTS idx_hashtags_post_id ON hashtags(post_id);
CREATE INDEX IF NOT EXISTS idx_hashtags_tag     ON hashtags(tag);

-- Mentions
CREATE TABLE IF NOT EXISTS mentions (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    actor_uri  TEXT    NOT NULL,
    username   TEXT    NOT NULL DEFAULT '',
    created_at TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_mentions_post_id ON mentions(post_id);

-- Default settings (inserted only if not present)
INSERT OR IGNORE INTO settings(key, value) VALUES
    ('domain',              'example.com'),
    ('admin_password_hash', ''),
    ('max_log_rows',        '10000'),
    ('media_max_bytes',     '10485760'),
    ('version',             '1.0.0');
