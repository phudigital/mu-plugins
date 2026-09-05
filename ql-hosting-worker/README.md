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

Secret phải có trong **version đang nhận traffic**. Thêm Secret trên Dashboard có thể chỉ tạo version mới; nếu chỉ lưu version mà chưa deploy, trang vẫn chạy cấu hình cũ. Sau khi thêm Secret, chọn Deploy cho version đó (100% traffic), hoặc kiểm tra bằng Node 22:

```bash
npx wrangler versions list
npx wrangler deployments list
npx wrangler versions view <VERSION_ID>
npx wrangler versions deploy <VERSION_ID>@100
```

`wrangler secret list` chỉ xác nhận Secret đã được lưu, chưa chứng minh version production đã nhận Secret. Không đưa giá trị Secret vào lệnh, log hoặc repository.

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

## Kiểm tra đăng nhập

- Lỗi hiện ngay trong form đăng nhập và giữ nguyên cho tới lần thử tiếp theo.
- `503` thiếu `ADMIN_PASSWORD`/`JWT_SECRET`: kiểm tra Secret và version đang deploy. `503` lỗi Turnstile Secret/dịch vụ được phân biệt với `400` token thiếu, hết hạn, dùng lại hoặc sai hostname.
- `401` tài khoản/mật khẩu không đúng sau khi gửi token hợp lệ: Turnstile đã qua, cần kiểm tra mật khẩu nhập so với `ADMIN_PASSWORD`.
- Widget hiện dùng site key `0x4AAAAAAElwX3Sma_XFYZb4`, cho phép `pdl.vn` và các subdomain. Server chỉ nhận token được tạo tại hostname của `APP_ORIGIN` (`hosting.pdl.vn`).
- Login thành công phải trả cookie `qlh_session` (Secure, HttpOnly, SameSite=Strict); request `/api/data` tiếp theo phải trả `200`. Dashboard chỉ xuất hiện sau khi dữ liệu tải và render thành công. Cookie bị hỏng được coi là phiên hết hạn.
- `npm run build` bao gồm test Worker/D1 cục bộ cho login đầu tiên, login đồng thời, sai credential, token Turnstile, Secret thiếu, cookie và logout. Các test dùng credential riêng và mô phỏng Siteverify, không gọi production/Telegram.

Sự cố 05/09/2026: `ADMIN_PASSWORD` được lưu ở version `6784411b-2cb8-40f6-91e0-f6a04aed554b`, trong khi production vẫn dùng `b8c0a8aa-1baa-4c3c-b884-8e947262d7c6`. Đã deploy version chứa Secret ở 100% traffic; xác minh bằng trình duyệt rằng Turnstile qua được server và mật khẩu thử sai trả đúng `401`.

Bản sửa xử lý lỗi đã deploy ngày 05/09/2026: `69744bd8-8f2f-41aa-aacd-35595d09bf84`, gồm đủ 4 Secrets. Kiểm tra production: token không hợp lệ trả `400`, cookie hỏng vẫn cho `/api/status` trả `200` với `authenticated: false`, `/api/data` thiếu phiên trả `401`. Bộ kiểm tra cục bộ: 19 test đạt.
