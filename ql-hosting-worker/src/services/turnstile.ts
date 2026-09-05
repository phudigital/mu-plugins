import type { Env } from "./types";

export class TurnstileError extends Error {
  constructor(message: string, public readonly status: number, public readonly code: string) {
    super(message);
    this.name = "TurnstileError";
  }
}

export async function verifyTurnstile(env: Env, token: string, remoteIp?: string): Promise<void> {
  if (!env.TURNSTILE_SECRET_KEY) {
    const error = new Error("Chưa cấu hình TURNSTILE_SECRET_KEY.");
    error.name = "ConfigMissing";
    throw error;
  }
  if (!token || token.length > 2048) {
    throw new TurnstileError("Vui lòng hoàn tất Turnstile.", 400, "turnstile_token_missing_or_invalid");
  }

  const body = new FormData();
  body.append("secret", env.TURNSTILE_SECRET_KEY);
  body.append("response", token);
  if (remoteIp) body.append("remoteip", remoteIp);

  let result: { success?: boolean; hostname?: string; "error-codes"?: string[] };
  try {
    const response = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
      method: "POST",
      body,
      signal: AbortSignal.timeout(10000)
    });
    if (!response.ok) throw new Error("Siteverify unavailable");
    result = await response.json();
    if (!result || typeof result.success !== "boolean") throw new Error("Invalid Siteverify response");
  } catch {
    throw new TurnstileError("Không kết nối được dịch vụ xác minh Turnstile. Vui lòng thử lại.", 503, "turnstile_unavailable");
  }
  if (!result.success) {
    const codes = Array.isArray(result["error-codes"]) ? result["error-codes"] : [];
    if (codes.includes("missing-input-secret") || codes.includes("invalid-input-secret")) {
      throw new TurnstileError("TURNSTILE_SECRET_KEY không hợp lệ. Cần kiểm tra Secret của widget và deploy lại.", 503, "turnstile_secret_invalid");
    }
    if (codes.includes("internal-error")) {
      throw new TurnstileError("Dịch vụ Turnstile tạm thời gặp lỗi. Vui lòng thử lại.", 503, "turnstile_unavailable");
    }
    throw new TurnstileError("Turnstile không hợp lệ hoặc đã hết hạn. Vui lòng xác minh lại.", 400, "turnstile_token_invalid");
  }
  if (result.hostname !== new URL(env.APP_ORIGIN).hostname) {
    throw new TurnstileError("Turnstile không khớp tên miền đăng nhập. Vui lòng tải lại trang.", 400, "turnstile_hostname_mismatch");
  }
}
