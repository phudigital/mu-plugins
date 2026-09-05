import { decryptSecret } from "./crypto";
import { normalizeDateString } from "./normalize";
import type { BrandDocument, Env, SettingsDocument } from "./types";

export function daysUntilVietnam(dateValue: string, now = new Date()): number | null {
  const normalized = normalizeDateString(dateValue);
  if (!normalized) return null;
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Ho_Chi_Minh",
    year: "numeric",
    month: "2-digit",
    day: "2-digit"
  }).formatToParts(now);
  const get = (type: string) => Number(parts.find((part) => part.type === type)?.value || "0");
  const todayUtc = Date.UTC(get("year"), get("month") - 1, get("day"));
  const [year, month, day] = normalized.split("-").map(Number);
  const targetUtc = Date.UTC(year, month - 1, day);
  return Math.round((targetUtc - todayUtc) / 86400000);
}

function escapeTelegramHtml(value: string): string {
  return value.replace(/[&<>"']/g, (char) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    "\"": "&quot;",
    "'": "&#39;"
  }[char] || char));
}

function shouldSend(days: number, settings: SettingsDocument): boolean {
  if (settings.reminders.days.includes(days)) return true;
  return days < 0 && settings.reminders.notify_overdue;
}

async function recentlySent(env: Env, fingerprint: string, repeatAfterDays: number, now: Date): Promise<boolean> {
  const row = await env.DB.prepare("SELECT last_sent_at FROM notification_log WHERE fingerprint = ?")
    .bind(fingerprint)
    .first<{ last_sent_at: string }>();
  if (!row?.last_sent_at) return false;
  const last = Date.parse(row.last_sent_at);
  if (!Number.isFinite(last)) return false;
  return last + repeatAfterDays * 86400000 > now.getTime();
}

async function markSent(env: Env, fingerprint: string, domain: string, expire: string, milestone: string, now: Date): Promise<void> {
  await env.DB.prepare(`
    INSERT INTO notification_log (fingerprint, domain, expire, milestone, last_sent_at, status, updated_at)
    VALUES (?, ?, ?, ?, ?, 'sent', ?)
    ON CONFLICT(fingerprint) DO UPDATE SET
      last_sent_at = excluded.last_sent_at,
      status = 'sent',
      error = NULL,
      updated_at = excluded.updated_at
  `).bind(fingerprint, domain, expire, milestone, now.toISOString(), now.toISOString()).run();
}

async function markError(env: Env, fingerprint: string, domain: string, expire: string, milestone: string, error: string, now: Date): Promise<void> {
  await env.DB.prepare(`
    INSERT INTO notification_log (fingerprint, domain, expire, milestone, status, error, updated_at)
    VALUES (?, ?, ?, ?, 'error', ?, ?)
    ON CONFLICT(fingerprint) DO UPDATE SET
      status = 'error',
      error = excluded.error,
      updated_at = excluded.updated_at
  `).bind(fingerprint, domain, expire, milestone, error.slice(0, 240), now.toISOString()).run();
}

export async function sendTelegramText(env: Env, settings: SettingsDocument, text: string): Promise<string> {
  const token = await decryptSecret(env, settings.telegram.bot_token_encrypted || "");
  const chatId = settings.telegram.chat_id.trim();
  if (!token || !chatId) return "Thiếu bot token hoặc chat id Telegram.";

  const response = await fetch(`https://api.telegram.org/bot${encodeURIComponent(token)}/sendMessage`, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      chat_id: chatId,
      text,
      parse_mode: "HTML",
      disable_web_page_preview: "1"
    })
  });
  const result: { ok?: boolean; description?: string } = await response.json<{ ok?: boolean; description?: string }>().catch(() => ({ ok: false, description: "" }));
  return response.ok && result.ok ? "" : result.description || "Telegram trả về lỗi.";
}

export async function runReminders(env: Env, brand: BrandDocument, settings: SettingsDocument, dryRun: boolean, now = new Date()): Promise<{ ok: true; sent: string[]; skipped: string[] }> {
  const sent: string[] = [];
  const skipped: string[] = [];
  if (!settings.telegram.enabled) {
    return { ok: true, sent, skipped: ["Telegram đang tắt."] };
  }

  for (const [domain, info] of Object.entries(brand.domains)) {
    if (sent.length >= 45) {
      skipped.push("Còn domain cần xử lý, dừng để giữ giới hạn subrequest Free.");
      break;
    }
    const days = daysUntilVietnam(info.expire, now);
    if (days === null || !shouldSend(days, settings)) continue;
    const milestone = days < 0 ? "overdue" : String(days);
    const fingerprint = `${domain}|${info.expire}|${milestone}`;
    if (await recentlySent(env, fingerprint, settings.reminders.repeat_after_days, now)) {
      skipped.push(`${domain}: đã gửi gần đây`);
      continue;
    }

    const status = days < 0 ? `đã hết hạn ${Math.abs(days)} ngày` : `còn ${days} ngày`;
    const note = info.hosting_note ? `\nGhi chú: ${info.hosting_note}` : "";
    const message = escapeTelegramHtml(`PDL Hosting\nDomain: ${domain}\nHạn: ${info.expire}\nTrạng thái: ${status}${note}`);
    if (dryRun) {
      sent.push(domain);
      continue;
    }

    const error = await sendTelegramText(env, settings, message);
    if (error) {
      skipped.push(`${domain}: ${error}`);
      await markError(env, fingerprint, domain, info.expire, milestone, error, now);
    } else {
      sent.push(domain);
      await markSent(env, fingerprint, domain, info.expire, milestone, now);
    }
  }

  return { ok: true, sent, skipped };
}
