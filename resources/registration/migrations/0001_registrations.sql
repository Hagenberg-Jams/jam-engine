-- Registrations for a jam. Personal data stays in this database and is deleted after purge_after.
CREATE TABLE registrations (
    id TEXT PRIMARY KEY,
    year INTEGER NOT NULL,
    team_name TEXT NOT NULL,
    contact_email TEXT NOT NULL,
    programs TEXT NOT NULL DEFAULT '[]',
    wish TEXT NOT NULL,
    placement TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    people INTEGER NOT NULL,
    comment TEXT NOT NULL DEFAULT '',
    edit_token_hash TEXT NOT NULL UNIQUE,
    ip_hash TEXT NOT NULL DEFAULT '',
    consent_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    purge_after INTEGER NOT NULL
);

CREATE INDEX registrations_year ON registrations (year, placement, status);

CREATE TABLE members (
    registration_id TEXT NOT NULL REFERENCES registrations (id) ON DELETE CASCADE,
    position INTEGER NOT NULL,
    real_name TEXT NOT NULL,
    nickname TEXT NOT NULL DEFAULT '',
    show_as TEXT NOT NULL DEFAULT 'real',
    discord TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (registration_id, position)
);
