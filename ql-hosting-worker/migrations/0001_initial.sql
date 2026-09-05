CREATE TABLE IF NOT EXISTS documents (
  key TEXT PRIMARY KEY,
  json TEXT NOT NULL,
  version INTEGER NOT NULL DEFAULT 1,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE TABLE IF NOT EXISTS auth_state (
  id INTEGER PRIMARY KEY CHECK (id = 1),
  username TEXT NOT NULL,
  password_record TEXT NOT NULL,
  auth_version INTEGER NOT NULL DEFAULT 1,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE TABLE IF NOT EXISTS notification_log (
  fingerprint TEXT PRIMARY KEY,
  domain TEXT NOT NULL,
  expire TEXT NOT NULL,
  milestone TEXT NOT NULL,
  last_sent_at TEXT,
  status TEXT NOT NULL DEFAULT 'pending',
  claim_token TEXT,
  lease_until TEXT,
  error TEXT,
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS notification_log_domain_expire_milestone
  ON notification_log (domain, expire, milestone);

CREATE TABLE IF NOT EXISTS brand_revisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  document_key TEXT NOT NULL,
  version INTEGER NOT NULL,
  json TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

CREATE INDEX IF NOT EXISTS brand_revisions_document_version
  ON brand_revisions (document_key, version DESC);

INSERT OR IGNORE INTO documents (key, json, version)
VALUES
  ('brand', '{"company":"","address":"","website":"","logo":"","updated_at":"","notify":{"active":false,"type":"info","message":"","button_text":"","button_url":""},"contacts":[],"domains":{}}', 1),
  ('settings', '{"telegram":{"enabled":false,"chat_id":"","bot_token_encrypted":""},"reminders":{"days":[30,14,7,3,1,0],"notify_overdue":true,"repeat_after_days":1},"last_run":null}', 1);
