import { hashPassword } from "./crypto";
import { defaultBrand, defaultSettings, normalizeBrand, normalizeSettings, normalizeUsername } from "./normalize";
import type { AuthState, BrandDocument, DocumentRow, Env, PasswordRecord, SettingsDocument } from "./types";

export async function getDocument(env: Env, key: "brand" | "settings"): Promise<DocumentRow | null> {
  return env.DB.prepare("SELECT key, json, version, updated_at FROM documents WHERE key = ?").bind(key).first<DocumentRow>();
}

export async function getBrand(env: Env): Promise<{ brand: BrandDocument; version: number }> {
  const row = await getDocument(env, "brand");
  if (!row) return { brand: defaultBrand(), version: 0 };
  return { brand: normalizeBrand(JSON.parse(row.json)), version: row.version };
}

export async function getSettings(env: Env): Promise<{ settings: SettingsDocument; version: number }> {
  const row = await getDocument(env, "settings");
  if (!row) return { settings: defaultSettings(), version: 0 };
  return { settings: normalizeSettings(JSON.parse(row.json)), version: row.version };
}

export async function saveBrand(env: Env, brand: BrandDocument, expectedVersion: number): Promise<number> {
  const current = await getDocument(env, "brand");
  if (!current || current.version !== expectedVersion) return -1;
  const nextVersion = current.version + 1;
  const nextJson = JSON.stringify(brand);
  const revision = env.DB.prepare("INSERT INTO brand_revisions (document_key, version, json) VALUES ('brand', ?, ?)").bind(current.version, current.json);
  const update = env.DB.prepare("UPDATE documents SET json = ?, version = ?, updated_at = ? WHERE key = 'brand' AND version = ?")
    .bind(nextJson, nextVersion, new Date().toISOString(), current.version);
  const [, updateResult] = await env.DB.batch([revision, update]);
  if (!updateResult.meta || Number(updateResult.meta.changes || 0) < 1) return -1;
  await env.DB.prepare("DELETE FROM brand_revisions WHERE id NOT IN (SELECT id FROM brand_revisions WHERE document_key = 'brand' ORDER BY version DESC LIMIT 30)").run();
  return nextVersion;
}

export async function saveSettings(env: Env, settings: SettingsDocument, expectedVersion: number): Promise<number> {
  const current = await getDocument(env, "settings");
  if (!current || current.version !== expectedVersion) return -1;
  const nextVersion = current.version + 1;
  const result = await env.DB.prepare("UPDATE documents SET json = ?, version = ?, updated_at = ? WHERE key = 'settings' AND version = ?")
    .bind(JSON.stringify(settings), nextVersion, new Date().toISOString(), current.version)
    .run();
  if (!result.meta || Number(result.meta.changes || 0) < 1) return -1;
  return nextVersion;
}

export async function getAuthState(env: Env): Promise<AuthState | null> {
  const row = await env.DB.prepare("SELECT username, password_record, auth_version FROM auth_state WHERE id = 1")
    .first<{ username: string; password_record: string; auth_version: number }>();
  if (!row) return null;
  return {
    username: normalizeUsername(row.username),
    password_record: JSON.parse(row.password_record) as PasswordRecord,
    auth_version: row.auth_version
  };
}

export async function createAuthState(env: Env, username: string, password: string): Promise<AuthState | null> {
  const record = await hashPassword(password, env);
  const result = await env.DB.prepare("INSERT OR IGNORE INTO auth_state (id, username, password_record, auth_version, updated_at) VALUES (1, ?, ?, 1, ?)")
    .bind(normalizeUsername(username), JSON.stringify(record), new Date().toISOString())
    .run();
  if (!result.meta || Number(result.meta.changes || 0) < 1) return null;
  return {
    username: normalizeUsername(username),
    password_record: record,
    auth_version: 1
  };
}

export async function updateAuthState(env: Env, username: string, password?: string): Promise<AuthState> {
  const current = await getAuthState(env);
  if (!current) throw new Error("Chưa có tài khoản quản trị.");
  const record = password ? await hashPassword(password, env) : current.password_record;
  const authVersion = current.auth_version + (password ? 1 : 0);
  await env.DB.prepare("UPDATE auth_state SET username = ?, password_record = ?, auth_version = ?, updated_at = ? WHERE id = 1")
    .bind(normalizeUsername(username), JSON.stringify(record), authVersion, new Date().toISOString())
    .run();
  return {
    username: normalizeUsername(username),
    password_record: record,
    auth_version: authVersion
  };
}

export async function bumpAuthVersion(env: Env): Promise<void> {
  await env.DB.prepare("UPDATE auth_state SET auth_version = auth_version + 1, updated_at = ? WHERE id = 1")
    .bind(new Date().toISOString())
    .run();
}
