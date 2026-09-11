import { normalizeHostname, normalizeText } from "./normalize";
import type { CloudflareZoneInfo } from "./types";

const apiBase = "https://api.cloudflare.com/client/v4";
const pageSize = 50;
const maxPages = 20;

interface CloudflareEnvelope {
  success?: boolean;
  errors?: Array<{ message?: unknown }>;
  result?: unknown;
  result_info?: {
    total_pages?: unknown;
  };
}

export class CloudflareError extends Error {
  constructor(message: string, public readonly status = 502) {
    super(message);
    this.name = "CloudflareError";
  }
}

async function readPage(token: string, page: number, fetcher: typeof fetch): Promise<CloudflareEnvelope> {
  const url = new URL("/client/v4/zones", apiBase);
  url.searchParams.set("page", String(page));
  url.searchParams.set("per_page", String(pageSize));
  url.searchParams.set("order", "name");
  url.searchParams.set("direction", "asc");

  let response: Response;
  try {
    response = await fetcher(url, {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: "application/json"
      },
      signal: AbortSignal.timeout(20_000)
    });
  } catch (error) {
    const reason = error instanceof Error ? error.name : "NetworkError";
    throw new CloudflareError(`Không kết nối được Cloudflare (${reason}).`);
  }

  let payload: CloudflareEnvelope;
  try {
    payload = await response.json() as CloudflareEnvelope;
  } catch {
    throw new CloudflareError(`Cloudflare trả về dữ liệu không hợp lệ (HTTP ${response.status}).`);
  }

  if (!response.ok || payload.success !== true || !Array.isArray(payload.result)) {
    if (response.status === 401 || response.status === 403) {
      throw new CloudflareError("Token Cloudflare bị từ chối. Cần quyền Zone:Read cho tất cả zone cần đồng bộ.", 422);
    }
    const detail = (payload.errors || []).slice(0, 3).map((item) => normalizeText(item.message)).filter(Boolean).join("; ");
    throw new CloudflareError(detail ? `Cloudflare: ${detail}` : `Cloudflare trả về HTTP ${response.status}.`);
  }
  return payload;
}

export async function listCloudflareZones(tokenValue: unknown, fetcher: typeof fetch = fetch): Promise<CloudflareZoneInfo[]> {
  const token = normalizeText(tokenValue);
  if (!token) throw new CloudflareError("Chưa cấu hình API token Cloudflare.", 422);
  if (token.length > 1024) throw new CloudflareError("API token Cloudflare không hợp lệ.", 422);

  const zones: CloudflareZoneInfo[] = [];
  const seen = new Set<string>();
  let totalPages = 1;

  for (let page = 1; page <= totalPages; page += 1) {
    if (page > maxPages) {
      throw new CloudflareError(`Tài khoản có quá ${pageSize * maxPages} zone; dừng để tránh đồng bộ thiếu dữ liệu.`);
    }
    const payload = await readPage(token, page, fetcher);
    const reportedPages = Number(payload.result_info?.total_pages || 1);
    if (!Number.isInteger(reportedPages) || reportedPages < 1 || reportedPages > maxPages) {
      throw new CloudflareError("Cloudflare trả về thông tin phân trang không hợp lệ.");
    }
    totalPages = reportedPages;

    for (const value of payload.result as Array<Record<string, unknown>>) {
      const name = normalizeHostname(value.name);
      if (!name || seen.has(name)) continue;
      const account = value.account && typeof value.account === "object" ? value.account as Record<string, unknown> : {};
      seen.add(name);
      zones.push({
        name,
        status: normalizeText(value.status),
        paused: Boolean(value.paused),
        type: normalizeText(value.type),
        account_name: normalizeText(account.name)
      });
    }
  }

  return zones.sort((a, b) => a.name.localeCompare(b.name));
}
