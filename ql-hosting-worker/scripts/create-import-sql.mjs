import { readFileSync, writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, "../..");
const output = resolve(here, "import.sql");
const brandPath = process.argv[2] ? resolve(process.cwd(), process.argv[2]) : resolve(root, "ql-hosting/brand.json");
const settingsPath = process.argv[3] ? resolve(process.cwd(), process.argv[3]) : resolve(root, "ql-hosting/data/settings.json");

function sqlString(value) {
  return `'${String(value).replace(/'/g, "''")}'`;
}

function readJson(path, fallback) {
  try {
    return JSON.parse(readFileSync(path, "utf8"));
  } catch {
    return fallback;
  }
}

const brand = readJson(brandPath, null);
if (!brand || typeof brand !== "object") {
  throw new Error(`Không đọc được brand JSON: ${brandPath}`);
}

const oldSettings = readJson(settingsPath, {});
const settings = {
  telegram: {
    enabled: Boolean(oldSettings.telegram?.enabled),
    chat_id: String(oldSettings.telegram?.chat_id || ""),
    bot_token_encrypted: ""
  },
  reminders: {
    days: Array.isArray(oldSettings.reminders?.days) ? oldSettings.reminders.days : [30, 14, 7, 3, 1, 0],
    notify_overdue: oldSettings.reminders?.notify_overdue !== false,
    repeat_after_days: Math.max(1, Number(oldSettings.reminders?.repeat_after_days || 1))
  },
  last_run: null
};

const sql = [
  `INSERT INTO documents (key, json, version, updated_at) VALUES ('brand', ${sqlString(JSON.stringify(brand))}, 1, ${sqlString(new Date().toISOString())}) ON CONFLICT(key) DO UPDATE SET json = excluded.json, version = documents.version + 1, updated_at = excluded.updated_at;`,
  `INSERT INTO documents (key, json, version, updated_at) VALUES ('settings', ${sqlString(JSON.stringify(settings))}, 1, ${sqlString(new Date().toISOString())}) ON CONFLICT(key) DO UPDATE SET json = excluded.json, version = documents.version + 1, updated_at = excluded.updated_at;`,
  ""
].join("\n");

writeFileSync(output, sql);
console.log(`Đã tạo ${output}`);
console.log("SQL này không chứa password hash hoặc Telegram bot token.");
