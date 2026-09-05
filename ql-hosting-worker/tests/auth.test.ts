import { readFileSync } from "node:fs";
import { build } from "esbuild";
import { convertV4MiniflareOptions, Miniflare, Response as TestResponse } from "miniflare";
import { afterAll, beforeAll, beforeEach, describe, expect, it } from "vitest";

const origin = "https://hosting.pdl.vn";
const bindings = {
  APP_ORIGIN: origin,
  ADMIN_USERNAME: "phudigital",
  ADMIN_PASSWORD: "local-test-password",
  JWT_SECRET: "local-only-session-secret",
  SETTINGS_ENCRYPTION_KEY: "local-only-encryption-secret",
  TURNSTILE_SECRET_KEY: "local-only-turnstile-secret",
  TURNSTILE_SITE_KEY: "local-only-site-key"
};

describe("Worker login and dashboard session", () => {
  let worker: Miniflare;
  let script: string;
  let verifiedTokens = new Set<string>();

  function options(overrides: Partial<typeof bindings> = {}) {
    return convertV4MiniflareOptions({
      modules: true,
      script,
      compatibilityDate: "2026-09-05",
      cf: false as const,
      d1Databases: ["DB"],
      bindings: { ...bindings, ...overrides },
      outboundService: async (request) => {
        // Isolate external calls: these tests never contact Cloudflare or Telegram.
        expect(request.url).toBe("https://challenges.cloudflare.com/turnstile/v0/siteverify");
        const form = await request.formData();
        expect(form.get("secret")).toBe(bindings.TURNSTILE_SECRET_KEY);
        const token = String(form.get("response"));
        if (token === "unavailable") return new TestResponse("Unavailable", { status: 503 });
        if (token === "invalid-json") return new TestResponse("invalid json");
        if (token === "bad-secret") return TestResponse.json({ success: false, "error-codes": ["invalid-input-secret"] });
        if (token === "expired" || verifiedTokens.has(token)) {
          return TestResponse.json({ success: false, "error-codes": ["timeout-or-duplicate"] });
        }
        verifiedTokens.add(token);
        return TestResponse.json({ success: true, hostname: token === "wrong-host" ? "app.pdl.vn" : "hosting.pdl.vn" });
      }
    });
  }

  async function login(token = "valid", password = bindings.ADMIN_PASSWORD, username = "phudigital") {
    return worker.dispatchFetch(`${origin}/api/login`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Origin: origin },
      body: JSON.stringify({ username, password, cf_turnstile_response: token })
    });
  }

  beforeAll(async () => {
    const bundled = await build({ entryPoints: ["src/index.ts"], bundle: true, format: "esm", platform: "browser", target: "es2022", write: false });
    script = bundled.outputFiles[0].text;
    worker = new Miniflare(options());
    const db = await worker.getD1Database("DB");
    const migration = readFileSync("migrations/0001_initial.sql", "utf8");
    await db.batch(migration.split(";").map(sql => sql.trim()).filter(Boolean).map(sql => db.prepare(sql)));
  }, 30000);

  beforeEach(async () => {
    verifiedTokens = new Set();
    const db = await worker.getD1Database("DB");
    await db.prepare("DELETE FROM auth_state").run();
  });

  afterAll(async () => { await worker?.dispose(); });

  it("creates the first account, serves dashboard data with its cookie, and revokes the session on logout", async () => {
    const response = await login();
    expect(response.status).toBe(200);
    const setCookie = response.headers.get("set-cookie") || "";
    expect(setCookie).toContain("HttpOnly; Secure; SameSite=Strict; Max-Age=28800");
    const headers = { Cookie: setCookie.split(";")[0] };
    const status = await worker.dispatchFetch(`${origin}/api/status`, { headers });
    expect(await status.json()).toMatchObject({ authenticated: true });
    const data = await worker.dispatchFetch(`${origin}/api/data`, { headers });
    expect(data.status).toBe(200);
    expect(await data.json()).toMatchObject({ ok: true, settings: { username: "phudigital" }, brand_version: 1, settings_version: 1 });
    const logout = await worker.dispatchFetch(`${origin}/api/logout`, {
      method: "POST", headers: { ...headers, Origin: origin, "Content-Type": "application/json" }, body: "{}"
    });
    expect(logout.status).toBe(200);
    expect((await worker.dispatchFetch(`${origin}/api/data`, { headers })).status).toBe(401);
  });

  it("allows two concurrent first logins without failing account initialization", async () => {
    const responses = await Promise.all([login("first"), login("second")]);
    expect(responses.map(response => response.status)).toEqual([200, 200]);
    const db = await worker.getD1Database("DB");
    expect(await db.prepare("SELECT COUNT(*) AS count FROM auth_state").first("count")).toBe(1);
  });

  it("rejects incorrect passwords and usernames after Turnstile without creating an account", async () => {
    expect((await login("wrong-password", "incorrect")).status).toBe(401);
    expect((await login("wrong-username", bindings.ADMIN_PASSWORD, "another-admin")).status).toBe(401);
    const db = await worker.getD1Database("DB");
    expect(await db.prepare("SELECT COUNT(*) AS count FROM auth_state").first("count")).toBe(0);
  });

  it.each(["", "expired", "wrong-host"])("rejects missing, expired, or wrong-host tokens (%s) as client errors", async token => {
    const response = await login(token);
    expect(response.status).toBe(400);
    expect(response.headers.has("set-cookie")).toBe(false);
  });

  it("rejects a token consumed by a failed login", async () => {
    expect((await login("single-use", "incorrect")).status).toBe(401);
    const replay = await login("single-use");
    expect(replay.status).toBe(400);
    expect(await replay.json()).toMatchObject({ code: "turnstile_token_invalid" });
  });

  it.each(["unavailable", "invalid-json", "bad-secret"])("reports Siteverify failures (%s) as service/configuration errors", async token => {
    const response = await login(token);
    expect(response.status).toBe(503);
    expect(response.headers.has("set-cookie")).toBe(false);
  });

  it.each(["ADMIN_PASSWORD", "JWT_SECRET", "TURNSTILE_SECRET_KEY"] as const)("fails closed when the active version lacks %s", async name => {
    await worker.setOptions(options({ [name]: "" }));
    try {
      const response = await login();
      expect(response.status).toBe(503);
      expect(await response.text()).toContain(name);
    } finally {
      await worker.setOptions(options());
    }
  }, 15000);

  it("treats a malformed session as logged out without returning a server error", async () => {
    const headers = { Cookie: "qlh_session=broken.payload.%25%25%25" };
    const status = await worker.dispatchFetch(`${origin}/api/status`, { headers });
    expect(status.status).toBe(200);
    expect(await status.json()).toMatchObject({ authenticated: false });
    expect((await worker.dispatchFetch(`${origin}/api/data`, { headers })).status).toBe(401);
  });
});
