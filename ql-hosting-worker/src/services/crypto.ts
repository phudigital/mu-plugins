import type { Env, PasswordRecord } from "./types";

const encoder = new TextEncoder();
const decoder = new TextDecoder();

function toArrayBuffer(bytes: Uint8Array): ArrayBuffer {
  return bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) as ArrayBuffer;
}

export function base64UrlEncode(bytes: ArrayBuffer | Uint8Array): string {
  const view = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);
  let binary = "";
  view.forEach((byte) => { binary += String.fromCharCode(byte); });
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

export function base64UrlDecode(value: string): Uint8Array {
  const padded = value.replace(/-/g, "+").replace(/_/g, "/").padEnd(Math.ceil(value.length / 4) * 4, "=");
  const binary = atob(padded);
  return Uint8Array.from(binary, (char) => char.charCodeAt(0));
}

export function randomToken(bytes = 32): string {
  const random = new Uint8Array(bytes);
  crypto.getRandomValues(random);
  return base64UrlEncode(random);
}

function iterationsFromEnv(env: Env): number {
  const parsed = Number(env.PBKDF2_ITERATIONS || "60000");
  return Number.isInteger(parsed) && parsed >= 10000 ? parsed : 60000;
}

export async function hashPassword(password: string, env: Env): Promise<PasswordRecord> {
  const salt = new Uint8Array(16);
  crypto.getRandomValues(salt);
  const iterations = iterationsFromEnv(env);
  const material = await crypto.subtle.importKey("raw", encoder.encode(password), "PBKDF2", false, ["deriveBits"]);
  const hash = await crypto.subtle.deriveBits({ name: "PBKDF2", hash: "SHA-256", salt: toArrayBuffer(salt), iterations }, material, 256);
  return {
    algorithm: "pbkdf2-sha256",
    salt: base64UrlEncode(salt),
    iterations,
    hash: base64UrlEncode(hash)
  };
}

export async function verifyPassword(password: string, record: PasswordRecord): Promise<boolean> {
  if (record.algorithm !== "pbkdf2-sha256") return false;
  const salt = base64UrlDecode(record.salt);
  const material = await crypto.subtle.importKey("raw", encoder.encode(password), "PBKDF2", false, ["deriveBits"]);
  const hash = new Uint8Array(await crypto.subtle.deriveBits({ name: "PBKDF2", hash: "SHA-256", salt: toArrayBuffer(salt), iterations: record.iterations }, material, 256));
  const expected = base64UrlDecode(record.hash);
  if (hash.length !== expected.length) return false;
  let diff = 0;
  hash.forEach((byte, index) => { diff |= byte ^ expected[index]; });
  return diff === 0;
}

async function hmacKey(secret: string): Promise<CryptoKey> {
  return crypto.subtle.importKey("raw", encoder.encode(secret), { name: "HMAC", hash: "SHA-256" }, false, ["sign", "verify"]);
}

export async function signSession(env: Env, payload: Record<string, unknown>): Promise<string> {
  if (!env.JWT_SECRET) throw new Error("Thiếu JWT_SECRET.");
  const header = base64UrlEncode(encoder.encode(JSON.stringify({ alg: "HS256", typ: "JWT" })));
  const body = base64UrlEncode(encoder.encode(JSON.stringify(payload)));
  const data = `${header}.${body}`;
  const signature = await crypto.subtle.sign("HMAC", await hmacKey(env.JWT_SECRET), encoder.encode(data));
  return `${data}.${base64UrlEncode(signature)}`;
}

export async function verifySession(env: Env, token: string): Promise<Record<string, unknown> | null> {
  if (!env.JWT_SECRET || !token) return null;
  const parts = token.split(".");
  if (parts.length !== 3) return null;
  const [header, body, signature] = parts;
  const ok = await crypto.subtle.verify("HMAC", await hmacKey(env.JWT_SECRET), toArrayBuffer(base64UrlDecode(signature)), encoder.encode(`${header}.${body}`));
  if (!ok) return null;
  try {
    const decoded = JSON.parse(decoder.decode(base64UrlDecode(body))) as Record<string, unknown>;
    if (decoded.exp && Number(decoded.exp) < Math.floor(Date.now() / 1000)) return null;
    if (decoded.iss !== "ql-hosting-worker" || decoded.aud !== "hosting.pdl.vn") return null;
    return decoded;
  } catch {
    return null;
  }
}

async function aesKey(secret: string): Promise<CryptoKey> {
  const digest = await crypto.subtle.digest("SHA-256", encoder.encode(secret));
  return crypto.subtle.importKey("raw", digest, "AES-GCM", false, ["encrypt", "decrypt"]);
}

export async function encryptSecret(env: Env, plaintext: string): Promise<string> {
  if (!env.SETTINGS_ENCRYPTION_KEY) throw new Error("Thiếu SETTINGS_ENCRYPTION_KEY.");
  const iv = new Uint8Array(12);
  crypto.getRandomValues(iv);
  const ciphertext = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, await aesKey(env.SETTINGS_ENCRYPTION_KEY), encoder.encode(plaintext));
  return `v1:${base64UrlEncode(iv)}:${base64UrlEncode(ciphertext)}`;
}

export async function decryptSecret(env: Env, stored = ""): Promise<string> {
  if (!stored) return "";
  if (!env.SETTINGS_ENCRYPTION_KEY) throw new Error("Thiếu SETTINGS_ENCRYPTION_KEY.");
  const [version, iv, ciphertext] = stored.split(":");
  if (version !== "v1" || !iv || !ciphertext) return "";
  const plaintext = await crypto.subtle.decrypt({ name: "AES-GCM", iv: toArrayBuffer(base64UrlDecode(iv)) }, await aesKey(env.SETTINGS_ENCRYPTION_KEY), toArrayBuffer(base64UrlDecode(ciphertext)));
  return decoder.decode(plaintext);
}

export function maskSecret(value: string): string {
  return value ? `${value.slice(0, 6)}...${value.slice(-4)}` : "";
}
