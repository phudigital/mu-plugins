CREATE TABLE IF NOT EXISTS domain_registrar (
  domain TEXT PRIMARY KEY,
  registrar TEXT NOT NULL DEFAULT '',
  provider TEXT NOT NULL DEFAULT '',
  source TEXT NOT NULL DEFAULT '',
  registered INTEGER,
  created_at TEXT NOT NULL DEFAULT '',
  expires_at TEXT NOT NULL DEFAULT '',
  nameservers_json TEXT NOT NULL DEFAULT '[]',
  statuses_json TEXT NOT NULL DEFAULT '[]',
  checked_at TEXT NOT NULL DEFAULT '',
  error TEXT NOT NULL DEFAULT '',
  provider_updated_at TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS domain_registrar_provider
  ON domain_registrar (provider, registrar);
