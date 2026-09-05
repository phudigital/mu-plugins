# QL Hosting Worker

Worker quản trị QL Hosting chạy tại `https://hosting.pdl.vn`.

## URL

- Admin: `https://hosting.pdl.vn/`
- Public JSON cho widget: `https://hosting.pdl.vn/brand.json`
- API quản trị: `https://hosting.pdl.vn/api/*`

## Secrets

Không commit secret vào repo. Tạo sau khi có project Cloudflare:

```bash
npx wrangler secret put JWT_SECRET
npx wrangler secret put SETTINGS_ENCRYPTION_KEY
npx wrangler secret put TURNSTILE_SECRET_KEY
npx wrangler secret put ADMIN_PASSWORD
```

Turnstile site key public đã cấu hình trong `wrangler.toml` và `public/index.html`:

```text
0x4AAAAAAElwX3Sma_XFYZb4
```

## D1

Tạo database rồi cập nhật `database_id` trong `wrangler.toml`.

```bash
npx wrangler d1 create qlhosting
npx wrangler d1 migrations apply qlhosting --remote
```

Sinh SQL import từ dữ liệu PHP hiện tại:

```bash
npm run import:sql
npx wrangler d1 execute qlhosting --remote --file ./scripts/import.sql
```

File import không chứa password hash hoặc Telegram bot token. Tài khoản quản trị cố định là `phudigital`; đặt mật khẩu cũ vào Worker Secret `ADMIN_PASSWORD`, sau đó đăng nhập và nhập lại bot token trong tab Bot.

## Deploy

Wrangler hiện yêu cầu Node `>=22`.

```bash
npm install
npm run build
npx wrangler deploy
```

Cron đang comment trong `wrangler.toml`. Chỉ bật sau khi tắt cron PHP, chạy dry-run production và xác nhận Telegram không gửi trùng.
