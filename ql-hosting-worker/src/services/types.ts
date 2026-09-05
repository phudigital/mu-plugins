export type NotifyType = "info" | "warning" | "error" | "success";

export interface NotifyConfig {
  active: boolean;
  type: NotifyType;
  message: string;
  button_text: string;
  button_url: string;
}

export interface Contact {
  label?: string;
  phone?: string;
  display?: string;
  link_url?: string;
  email?: string;
  url?: string;
}

export interface DomainInfo {
  expire: string;
  hosting_note: string;
  notify: NotifyConfig;
}

export interface BrandDocument {
  company: string;
  address: string;
  website: string;
  logo: string;
  updated_at: string;
  notify: NotifyConfig;
  contacts: Contact[];
  domains: Record<string, DomainInfo>;
}

export interface TelegramSettings {
  enabled: boolean;
  chat_id: string;
  bot_token_encrypted?: string;
  bot_token_masked?: string;
  has_bot_token?: boolean;
}

export interface ReminderSettings {
  days: number[];
  notify_overdue: boolean;
  repeat_after_days: number;
}

export interface RunSummary {
  started_at: string;
  sent: string[];
  skipped: string[];
}

export interface SettingsDocument {
  telegram: TelegramSettings;
  reminders: ReminderSettings;
  last_run: RunSummary | null;
}

export interface PublicSettings extends Omit<SettingsDocument, "telegram"> {
  username: string;
  telegram: Omit<TelegramSettings, "bot_token_encrypted">;
}

export interface DocumentRow {
  key: string;
  json: string;
  version: number;
  updated_at: string;
}

export interface PasswordRecord {
  algorithm: "pbkdf2-sha256";
  salt: string;
  iterations: number;
  hash: string;
}

export interface AuthState {
  username: string;
  password_record: PasswordRecord;
  auth_version: number;
}

export interface Env {
  DB: D1Database;
  ASSETS: Fetcher;
  APP_ORIGIN: string;
  TURNSTILE_SITE_KEY: string;
  TURNSTILE_SECRET_KEY?: string;
  JWT_SECRET?: string;
  SETTINGS_ENCRYPTION_KEY?: string;
  BOOTSTRAP_SECRET?: string;
  PBKDF2_ITERATIONS?: string;
}
