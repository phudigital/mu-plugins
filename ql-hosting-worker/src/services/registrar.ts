import { normalizeHostname, normalizeText } from "./normalize";
import type { DomainRegistrarRecord } from "./types";

const IANA_RDAP_BOOTSTRAP = "https://data.iana.org/rdap/dns.json";
const BKNS_WHOIS_ENDPOINT = "https://whois.bkns.vn/api/v1/whois";
const MAX_JSON_BYTES = 2 * 1024 * 1024;
const REQUEST_TIMEOUT_MS = 12_000;
const MAX_REDIRECTS = 3;

type Fetcher = typeof fetch;
type UnknownRecord = Record<string, unknown>;

interface BootstrapDocument {
  services?: unknown;
}

export class RegistrarLookupError extends Error {
  constructor(message: string, public status = 422, public code = "registrar_lookup_failed") {
    super(message);
    this.name = "RegistrarLookupError";
  }
}

export function normalizeLookupDomain(value: unknown): string {
  let text = normalizeText(value).toLocaleLowerCase("en-US");
  if (!text) return "";
  if (!/^[a-z][a-z0-9+.-]*:\/\//i.test(text)) text = `https://${text}`;
  try {
    text = new URL(text).hostname.replace(/^www\./, "").replace(/\.$/, "");
  } catch {
    return "";
  }
  const domain = normalizeHostname(text);
  if (!domain || domain.length > 253 || !domain.includes(".")) return "";
  const labels = domain.split(".");
  if (labels.some((label) => !label || label.length > 63 || label.startsWith("-") || label.endsWith("-"))) return "";
  return domain;
}

export function isVietnameseDomain(domain: string): boolean {
  return domain === "vn" || domain.endsWith(".vn");
}

function emptyRecord(domain: string, source: string): DomainRegistrarRecord {
  return {
    domain,
    registrar: "",
    provider: "",
    source,
    registered: null,
    created_at: "",
    expires_at: "",
    nameservers: [],
    statuses: [],
    checked_at: new Date().toISOString(),
    error: ""
  };
}

async function readJsonLimited(response: Response): Promise<UnknownRecord> {
  const announced = Number(response.headers.get("content-length") || "0");
  if (announced > MAX_JSON_BYTES) throw new RegistrarLookupError("Nguồn tra cứu trả dữ liệu quá lớn.", 502);
  if (!response.body) throw new RegistrarLookupError("Nguồn tra cứu trả phản hồi rỗng.", 502);

  const reader = response.body.getReader();
  const chunks: Uint8Array[] = [];
  let total = 0;
  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    total += value.byteLength;
    if (total > MAX_JSON_BYTES) {
      await reader.cancel();
      throw new RegistrarLookupError("Nguồn tra cứu trả dữ liệu quá lớn.", 502);
    }
    chunks.push(value);
  }

  const bytes = new Uint8Array(total);
  let offset = 0;
  for (const chunk of chunks) {
    bytes.set(chunk, offset);
    offset += chunk.byteLength;
  }
  try {
    const parsed = JSON.parse(new TextDecoder().decode(bytes));
    if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) throw new Error("shape");
    return parsed as UnknownRecord;
  } catch {
    throw new RegistrarLookupError("Nguồn tra cứu trả JSON không hợp lệ.", 502);
  }
}

function safeExternalUrl(value: string): URL {
  let url: URL;
  try {
    url = new URL(value);
  } catch {
    throw new RegistrarLookupError("Nguồn RDAP trả địa chỉ không hợp lệ.", 502);
  }
  const host = url.hostname.toLocaleLowerCase("en-US");
  const forbidden = host === "localhost" || host.endsWith(".localhost") || host.endsWith(".internal") || host.includes(":") ||
    /^(?:127\.|10\.|0\.|169\.254\.|192\.168\.|100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|172\.(?:1[6-9]|2\d|3[01])\.)/.test(host);
  if (url.protocol !== "https:" || forbidden || !host) {
    throw new RegistrarLookupError("Nguồn RDAP chuyển hướng tới địa chỉ không được phép.", 502);
  }
  return url;
}

async function fetchWithSafeRedirects(urlValue: string, init: RequestInit, fetcher: Fetcher): Promise<Response> {
  let url = safeExternalUrl(urlValue);
  for (let attempt = 0; attempt <= MAX_REDIRECTS; attempt += 1) {
    const response = await fetcher(url.toString(), {
      ...init,
      redirect: "manual",
      signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS)
    });
    if (![301, 302, 303, 307, 308].includes(response.status)) return response;
    const location = response.headers.get("location");
    if (!location || attempt === MAX_REDIRECTS) {
      throw new RegistrarLookupError("Nguồn RDAP chuyển hướng quá nhiều lần.", 502);
    }
    url = safeExternalUrl(new URL(location, url).toString());
  }
  throw new RegistrarLookupError("Không thể kết nối nguồn RDAP.", 502);
}

function arrayOfObjects(value: unknown): UnknownRecord[] {
  return Array.isArray(value) ? value.filter((item): item is UnknownRecord => Boolean(item) && typeof item === "object" && !Array.isArray(item)) : [];
}

function vcardName(entity: UnknownRecord): string {
  const vcard = entity.vcardArray;
  if (!Array.isArray(vcard) || !Array.isArray(vcard[1])) return "";
  let organization = "";
  for (const field of vcard[1] as unknown[]) {
    if (!Array.isArray(field) || field.length < 4) continue;
    const key = String(field[0] || "").toLocaleLowerCase("en-US");
    const value = Array.isArray(field[3]) ? field[3].join(" ") : String(field[3] || "");
    if (key === "fn" && value.trim()) return value.trim();
    if (key === "org" && value.trim()) organization = value.trim();
  }
  return organization;
}

function rdapRegistrar(payload: UnknownRecord): string {
  const queue = arrayOfObjects(payload.entities);
  while (queue.length) {
    const entity = queue.shift()!;
    const roles = Array.isArray(entity.roles) ? entity.roles.map((role) => String(role).toLocaleLowerCase("en-US")) : [];
    if (roles.includes("registrar")) {
      const name = vcardName(entity);
      if (name) return name;
    }
    queue.push(...arrayOfObjects(entity.entities));
  }
  return "";
}

function eventDate(payload: UnknownRecord, actions: string[]): string {
  for (const event of arrayOfObjects(payload.events)) {
    const action = String(event.eventAction || "").toLocaleLowerCase("en-US");
    if (actions.includes(action)) return normalizeText(event.eventDate);
  }
  return "";
}

async function rdapBaseUrl(domain: string, fetcher: Fetcher): Promise<string> {
  const response = await fetcher(IANA_RDAP_BOOTSTRAP, {
    headers: { Accept: "application/json", "User-Agent": "QL-Hosting-PDL/0.3" },
    signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    cf: { cacheEverything: true, cacheTtl: 86_400 }
  } as RequestInit);
  if (!response.ok) throw new RegistrarLookupError(`Không đọc được danh mục RDAP của IANA (HTTP ${response.status}).`, 502);
  const payload = await readJsonLimited(response) as BootstrapDocument;
  const tld = domain.split(".").pop() || "";
  const services = Array.isArray(payload.services) ? payload.services : [];
  for (const service of services) {
    if (!Array.isArray(service) || !Array.isArray(service[0]) || !Array.isArray(service[1])) continue;
    const suffixes = service[0].map((value) => String(value).toLocaleLowerCase("en-US"));
    if (!suffixes.includes(tld)) continue;
    const base = service[1].map(String).find((value) => value.startsWith("https://"));
    if (base) return safeExternalUrl(base).toString();
  }
  return "";
}

function parseRdap(domain: string, payload: UnknownRecord): DomainRegistrarRecord {
  const record = emptyRecord(domain, "rdap");
  record.registered = true;
  record.registrar = rdapRegistrar(payload);
  record.created_at = eventDate(payload, ["registration"]);
  record.expires_at = eventDate(payload, ["expiration", "expiry"]);
  record.nameservers = Array.from(new Set(arrayOfObjects(payload.nameservers)
    .map((item) => normalizeText(item.ldhName).toLocaleLowerCase("en-US"))
    .filter(Boolean))).sort();
  record.statuses = Array.isArray(payload.status) ? payload.status.map(normalizeText).filter(Boolean) : [];
  if (!record.registrar) record.error = "RDAP không công bố tên nhà đăng ký.";
  return record;
}

async function lookupRdap(domain: string, fetcher: Fetcher): Promise<DomainRegistrarRecord> {
  const base = await rdapBaseUrl(domain, fetcher);
  if (!base) {
    const record = emptyRecord(domain, "rdap");
    record.error = "Hậu tố tên miền này chưa có dịch vụ RDAP công khai.";
    return record;
  }
  const url = new URL(`domain/${encodeURIComponent(domain)}`, base.endsWith("/") ? base : `${base}/`);
  const response = await fetchWithSafeRedirects(url.toString(), {
    headers: { Accept: "application/rdap+json, application/json", "User-Agent": "QL-Hosting-PDL/0.3" },
    cf: { cacheEverything: true, cacheTtl: 43_200 }
  } as RequestInit, fetcher);
  if (response.status === 404) {
    const record = emptyRecord(domain, "rdap");
    record.registered = false;
    record.error = "Không tìm thấy dữ liệu đăng ký; tên miền có thể chưa được đăng ký.";
    return record;
  }
  if (!response.ok) throw new RegistrarLookupError(`Dịch vụ RDAP phản hồi HTTP ${response.status}.`, response.status === 429 ? 429 : 502);
  return parseRdap(domain, await readJsonLimited(response));
}

async function lookupBkns(domain: string, apiKey: string, fetcher: Fetcher): Promise<DomainRegistrarRecord> {
  if (!apiKey) throw new RegistrarLookupError("Tên miền .vn cần API key BKNS. Nhập key trong mục cấu hình bên dưới rồi bấm Lưu.", 422, "bkns_key_required");
  const url = new URL(BKNS_WHOIS_ENDPOINT);
  url.searchParams.set("domain", domain);
  const response = await fetcher(url.toString(), {
    headers: { Accept: "application/json", "X-API-Key": apiKey, "User-Agent": "QL-Hosting-PDL/0.3" },
    signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS)
  });
  if (response.status === 429) throw new RegistrarLookupError("BKNS đang giới hạn tần suất. Key demo chỉ cho 2 lượt/phút; hãy thử lại sau.", 429, "bkns_rate_limited");
  if (response.status === 401 || response.status === 403) throw new RegistrarLookupError("API key BKNS không hợp lệ hoặc không có quyền tra cứu.", 422, "bkns_key_invalid");
  if (!response.ok) throw new RegistrarLookupError(`BKNS phản hồi HTTP ${response.status}.`, 502);
  const payload = await readJsonLimited(response);
  const record = emptyRecord(domain, "bkns");
  if (payload.status === "available") {
    record.registered = false;
    record.error = "Tên miền chưa được đăng ký.";
    return record;
  }
  const data = payload.data && typeof payload.data === "object" ? payload.data as UnknownRecord : {};
  const dates = data.dates && typeof data.dates === "object" ? data.dates as UnknownRecord : {};
  const registrar = data.registrar && typeof data.registrar === "object" ? data.registrar as UnknownRecord : {};
  record.registered = true;
  record.registrar = normalizeText(registrar.name);
  record.created_at = normalizeText(dates.created);
  record.expires_at = normalizeText(dates.expiry || dates.renewalDeadline);
  record.nameservers = Array.isArray(data.nameservers) ? data.nameservers.map((item) => normalizeText(item).toLocaleLowerCase("en-US")).filter(Boolean).sort() : [];
  record.statuses = Array.isArray(data.domainStatus) ? data.domainStatus.map(normalizeText).filter(Boolean) : [];
  if (!record.registrar) record.error = "BKNS không công bố tên nhà đăng ký.";
  return record;
}

export async function lookupDomainRegistrar(domainValue: unknown, bknsApiKey = "", fetcher: Fetcher = fetch): Promise<DomainRegistrarRecord> {
  const domain = normalizeLookupDomain(domainValue);
  if (!domain) throw new RegistrarLookupError("Tên miền không hợp lệ.", 422, "invalid_domain");
  try {
    return isVietnameseDomain(domain)
      ? await lookupBkns(domain, bknsApiKey, fetcher)
      : await lookupRdap(domain, fetcher);
  } catch (error) {
    if (error instanceof RegistrarLookupError) throw error;
    const message = error instanceof Error && error.name === "TimeoutError"
      ? "Nguồn tra cứu phản hồi quá chậm. Vui lòng thử lại."
      : "Không thể kết nối nguồn tra cứu tên miền.";
    throw new RegistrarLookupError(message, 502);
  }
}
