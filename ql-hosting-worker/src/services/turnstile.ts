import type { Env } from "./types";

export async function verifyTurnstile(env: Env, token: string, remoteIp?: string): Promise<void> {
  if (!env.TURNSTILE_SECRET_KEY) {
    throw new Error("Chưa cấu hình TURNSTILE_SECRET_KEY.");
  }
  if (!token) {
    throw new Error("Vui lòng hoàn tất Turnstile.");
  }

  const body = new FormData();
  body.append("secret", env.TURNSTILE_SECRET_KEY);
  body.append("response", token);
  if (remoteIp) body.append("remoteip", remoteIp);

  const response = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
    method: "POST",
    body
  });
  const result = await response.json<{ success?: boolean; "error-codes"?: string[] }>().catch(() => ({ success: false }));
  if (!response.ok || !result.success) {
    throw new Error("Turnstile không hợp lệ hoặc đã hết hạn.");
  }
}
