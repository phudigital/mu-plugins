import { Hono } from "hono";
import type { Context } from "hono";
import { getCookie } from "hono/cookie";
import { decryptSecret, encryptSecret, maskSecret, signSession, verifyPassword, verifySession } from "./services/crypto";
import { defaultSettings, normalizeBrand, normalizeSettings, normalizeUsername } from "./services/normalize";
import { runReminders, sendTelegramText } from "./services/reminders";
import { bumpAuthVersion, createAuthState, getAuthState, getBrand, getSettings, saveBrand, saveSettings, updateAuthState } from "./services/storage";
import { verifyTurnstile } from "./services/turnstile";
import type { Env, PublicSettings } from "./services/types";

type Variables = {
  auth: {
    username: string;
    authVersion: number;
  };
};

const app = new Hono<{ Bindings: Env; Variables: Variables }>();
const sessionCookie = "qlh_session";
const maxBodyBytes = 256 * 1024;
type AppContext = Context<{ Bindings: Env; Variables: Variables }>;

function json(payload: unknown, status = 200, headers: HeadersInit = {}) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "no-store",
      ...headers
    }
  });
}

function sessionCookieHeader(value: string, maxAge: number): string {
  const encoded = encodeURIComponent(value);
  const expires = maxAge <= 0 ? "; Expires=Thu, 01 Jan 1970 00:00:00 GMT" : "";
  return `${sessionCookie}=${encoded}; Path=/; HttpOnly; Secure; SameSite=Strict; Max-Age=${maxAge}${expires}`;
}

function attachSession(response: Response, token: string): Response {
  response.headers.append("Set-Cookie", sessionCookieHeader(token, 8 * 60 * 60));
  return response;
}

function clearSession(response: Response): Response {
  response.headers.append("Set-Cookie", sessionCookieHeader("", 0));
  return response;
}

async function readJson(request: Request): Promise<Record<string, unknown>> {
  const size = Number(request.headers.get("content-length") || "0");
  if (size > maxBodyBytes) {
    const error = new Error("Payload vượt quá giới hạn 256 KiB.");
    error.name = "PayloadTooLarge";
    throw error;
  }
  const text = await request.text();
  if (!text.trim()) return {};
  if (new TextEncoder().encode(text).byteLength > maxBodyBytes) {
    const error = new Error("Payload vượt quá giới hạn 256 KiB.");
    error.name = "PayloadTooLarge";
    throw error;
  }
  try {
    const parsed = JSON.parse(text);
    return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
  } catch {
    const error = new Error("Payload không phải JSON hợp lệ.");
    error.name = "BadJson";
    throw error;
  }
}

function originAllowed(env: Env, request: Request): boolean {
  const origin = request.headers.get("origin");
  if (!origin) return true;
  return origin === env.APP_ORIGIN;
}

app.use("*", async (c, next) => {
  c.header("X-Content-Type-Options", "nosniff");
  c.header("Referrer-Policy", "same-origin");
  c.header("Permissions-Policy", "camera=(), microphone=(), geolocation=()");
  await next();
});

app.use("/api/*", async (c, next) => {
  if (c.req.method !== "GET" && !originAllowed(c.env, c.req.raw)) {
    return json({ ok: false, message: "Origin không hợp lệ." }, 403);
  }
  if (c.req.method !== "GET") {
    const contentType = c.req.header("content-type") || "";
    if (!contentType.includes("application/json")) {
      return json({ ok: false, message: "Payload cần Content-Type application/json." }, 415);
    }
  }
  await next();
});

function edgeCache(): Cache {
  return (caches as CacheStorage & { default: Cache }).default;
}

async function authenticate(c: AppContext): Promise<Response | null> {
  const token = getCookie(c, sessionCookie) || "";
  const payload = await verifySession(c.env, token);
  if (!payload) return json({ ok: false, message: "Bạn cần đăng nhập." }, 401);
  const auth = await getAuthState(c.env);
  if (!auth || auth.username !== payload.sub || auth.auth_version !== Number(payload.auth_version)) {
    return clearSession(json({ ok: false, message: "Phiên đã hết hạn. Vui lòng đăng nhập lại." }, 401));
  }
  c.set("auth", { username: auth.username, authVersion: auth.auth_version });
  return null;
}

async function issueSession(env: Env, username: string, authVersion: number): Promise<string> {
  const now = Math.floor(Date.now() / 1000);
  return signSession(env, {
    iss: "ql-hosting-worker",
    aud: "hosting.pdl.vn",
    sub: username,
    auth_version: authVersion,
    iat: now,
    exp: now + 8 * 60 * 60
  });
}

async function publicSettings(env: Env, username: string, settingsVersion?: number): Promise<PublicSettings & { _version?: number }> {
  const { settings, version } = await getSettings(env);
  let token = "";
  if (settings.telegram.bot_token_encrypted) {
    try {
      token = await decryptSecret(env, settings.telegram.bot_token_encrypted);
    } catch {
      token = "";
    }
  }
  return {
    username,
    telegram: {
      enabled: settings.telegram.enabled,
      chat_id: settings.telegram.chat_id,
      bot_token_masked: maskSecret(token),
      has_bot_token: Boolean(token)
    },
    reminders: settings.reminders,
    last_run: settings.last_run,
    _version: settingsVersion ?? version
  };
}

app.get("/ql-hosting", (c) => c.redirect("/", 308));
app.get("/ql-hosting/*", (c) => {
  const url = new URL(c.req.url);
  url.pathname = url.pathname.replace(/^\/ql-hosting/, "") || "/";
  return c.redirect(url.toString(), 308);
});

app.get("/api/status", async (c) => {
  const auth = await getAuthState(c.env);
  const token = getCookie(c, sessionCookie) || "";
  const payload = await verifySession(c.env, token);
  const authenticated = Boolean(auth && payload && auth.username === payload.sub && auth.auth_version === Number(payload.auth_version));
  return json({
    ok: true,
    authenticated,
    setup_required: !auth,
    turnstile_site_key: c.env.TURNSTILE_SITE_KEY
  });
});

app.post("/api/setup", async (c) => {
  const current = await getAuthState(c.env);
  if (current) return json({ ok: false, message: "App đã được thiết lập." }, 409);
  const payload = await readJson(c.req.raw);
  await verifyTurnstile(c.env, String(payload.cf_turnstile_response || ""), c.req.header("cf-connecting-ip"));
  if (c.env.BOOTSTRAP_SECRET && payload.bootstrap_secret !== c.env.BOOTSTRAP_SECRET) {
    return json({ ok: false, message: "Bootstrap secret không hợp lệ." }, 403);
  }
  if (!c.env.BOOTSTRAP_SECRET) {
    return json({ ok: false, message: "Chưa cấu hình BOOTSTRAP_SECRET." }, 503);
  }
  const username = normalizeUsername(payload.username || "phudigital");
  const password = String(payload.password || "");
  if (!username) return json({ ok: false, message: "Bạn cần nhập tài khoản." }, 422);
  if ([...password].length < 8) return json({ ok: false, message: "Mật khẩu cần tối thiểu 8 ký tự." }, 422);
  const auth = await createAuthState(c.env, username, password);
  if (!auth) return json({ ok: false, message: "Tài khoản đã được tạo bởi request khác." }, 409);
  const token = await issueSession(c.env, auth.username, auth.auth_version);
  return attachSession(json({ ok: true, message: "Đã tạo tài khoản quản trị." }), token);
});

app.post("/api/login", async (c) => {
  const auth = await getAuthState(c.env);
  if (!auth) return json({ ok: false, setup_required: true, message: "Cần tạo mật khẩu quản trị." }, 403);
  const payload = await readJson(c.req.raw);
  await verifyTurnstile(c.env, String(payload.cf_turnstile_response || ""), c.req.header("cf-connecting-ip"));
  const username = normalizeUsername(payload.username || "phudigital");
  const password = String(payload.password || "");
  if (auth.username !== username || !(await verifyPassword(password, auth.password_record))) {
    return json({ ok: false, message: "Tài khoản hoặc mật khẩu không đúng." }, 401);
  }
  const token = await issueSession(c.env, auth.username, auth.auth_version);
  return attachSession(json({ ok: true, message: "Đã đăng nhập." }), token);
});

app.post("/api/logout", async (c) => {
  await bumpAuthVersion(c.env);
  return clearSession(json({ ok: true, message: "Đã đăng xuất." }));
});

app.use("/api/data", async (c, next) => (await authenticate(c)) || next());
app.use("/api/save-brand", async (c, next) => (await authenticate(c)) || next());
app.use("/api/save-settings", async (c, next) => (await authenticate(c)) || next());
app.use("/api/test-telegram", async (c, next) => (await authenticate(c)) || next());
app.use("/api/run-reminders", async (c, next) => (await authenticate(c)) || next());

app.get("/api/data", async (c) => {
  const auth = await getAuthState(c.env);
  const { brand, version: brandVersion } = await getBrand(c.env);
  const settings = auth ? await publicSettings(c.env, auth.username) : { ...defaultSettings(), username: "phudigital" };
  return json({
    ok: true,
    brand,
    settings,
    brand_version: brandVersion,
    settings_version: "_version" in settings ? settings._version : 0
  });
});

app.post("/api/save-brand", async (c) => {
  const payload = await readJson(c.req.raw);
  const expected = Number(payload.version);
  if (!Number.isInteger(expected) || expected < 1) return json({ ok: false, message: "Thiếu version brand hợp lệ." }, 422);
  const brand = normalizeBrand(payload.brand);
  const nextVersion = await saveBrand(c.env, brand, expected);
  if (nextVersion < 0) return json({ ok: false, message: "Dữ liệu đã thay đổi ở phiên khác. Tải lại trước khi lưu." }, 409);
  c.executionCtx.waitUntil(edgeCache().delete(new URL("/brand.json", c.req.url).toString()));
  return json({ ok: true, message: "Đã lưu brand.json.", brand, version: nextVersion });
});

app.post("/api/save-settings", async (c) => {
  const auth = await getAuthState(c.env);
  if (!auth) return json({ ok: false, message: "Chưa có tài khoản quản trị." }, 403);
  const payload = await readJson(c.req.raw);
  const expected = Number(payload.version);
  if (!Number.isInteger(expected) || expected < 1) return json({ ok: false, message: "Thiếu version settings hợp lệ." }, 422);
  const incoming = payload.settings && typeof payload.settings === "object" ? payload.settings as Record<string, unknown> : {};
  const { settings: previous } = await getSettings(c.env);
  const next = normalizeSettings(incoming, previous);
  const telegram = incoming.telegram && typeof incoming.telegram === "object" ? incoming.telegram as Record<string, unknown> : {};
  if (typeof telegram.bot_token === "string" && telegram.bot_token.trim()) {
    next.telegram.bot_token_encrypted = await encryptSecret(c.env, telegram.bot_token.trim());
  }
  const username = normalizeUsername(incoming.username || auth.username);
  const newPassword = String(incoming.new_password || "");
  if (newPassword && [...newPassword].length < 8) {
    return json({ ok: false, message: "Mật khẩu mới cần tối thiểu 8 ký tự." }, 422);
  }
  const version = await saveSettings(c.env, next, expected);
  if (version < 0) return json({ ok: false, message: "Cài đặt đã thay đổi ở phiên khác. Tải lại trước khi lưu." }, 409);
  const authChanged = username !== auth.username || Boolean(newPassword);
  const nextAuth = authChanged ? await updateAuthState(c.env, username, newPassword || undefined) : auth;
  const response = json({
    ok: true,
    message: "Đã lưu cài đặt.",
    settings: await publicSettings(c.env, nextAuth.username, version),
    version
  });
  if (authChanged) {
    const token = await issueSession(c.env, nextAuth.username, nextAuth.auth_version);
    return attachSession(response, token);
  }
  return response;
});

app.post("/api/test-telegram", async (c) => {
  const { settings } = await getSettings(c.env);
  const error = await sendTelegramText(c.env, settings, `PDL ql-hosting: gửi thử Telegram thành công lúc ${new Date().toISOString()}`);
  return json({ ok: !error, message: error || "Đã gửi Telegram." }, error ? 422 : 200);
});

app.post("/api/run-reminders", async (c) => {
  const payload = await readJson(c.req.raw);
  const { brand } = await getBrand(c.env);
  const { settings, version } = await getSettings(c.env);
  const result = await runReminders(c.env, brand, settings, Boolean(payload.dry_run));
  if (!payload.dry_run) {
    const nextSettings = { ...settings, last_run: { started_at: new Date().toISOString(), sent: result.sent, skipped: result.skipped } };
    await saveSettings(c.env, nextSettings, version);
  }
  return json(result);
});

app.get("/brand.json", async (c) => {
  const cacheKey = new Request(new URL("/brand.json", c.req.url).toString(), { method: "GET" });
  const cached = await edgeCache().match(cacheKey);
  if (cached) return cached;
  const { brand } = await getBrand(c.env);
  const response = new Response(JSON.stringify(brand), {
    headers: {
      "Content-Type": "application/json; charset=utf-8",
      "Cache-Control": "public, max-age=60",
      "Access-Control-Allow-Origin": "*",
      "CDN-Cache-Control": "public, max-age=300"
    }
  });
  c.executionCtx.waitUntil(edgeCache().put(cacheKey, response.clone()));
  return response;
});

app.notFound((c) => json({ ok: false, message: "Không tìm thấy." }, 404));

app.onError((error) => {
  const status = error.name === "PayloadTooLarge" ? 413 : error.name === "BadJson" ? 400 : 500;
  return json({ ok: false, message: error.message || "Có lỗi xảy ra." }, status);
});

export default {
  fetch: app.fetch,
  async scheduled(_event: ScheduledEvent, env: Env, ctx: ExecutionContext) {
    ctx.waitUntil((async () => {
      const { brand } = await getBrand(env);
      const { settings, version } = await getSettings(env);
      const result = await runReminders(env, brand, settings, false);
      const nextSettings = { ...settings, last_run: { started_at: new Date().toISOString(), sent: result.sent, skipped: result.skipped } };
      await saveSettings(env, nextSettings, version);
    })());
  }
};
