# Kế hoạch chuyển QL Hosting sang Cloudflare Workers

Cập nhật: 05/09/2026. Trạng thái: đã triển khai Worker production, D1 và Secrets đăng nhập tại `hosting.pdl.vn`; tắt cron PHP và bật cron Worker vẫn chờ hoàn tất trước khi mở vận hành đầy đủ.

## 1. Mục tiêu và phạm vi

Chuyển `ql-hosting` từ `https://app.pdl.vn/ql-hosting` sang **domain root `https://hosting.pdl.vn`** bằng một Worker + Hono/TypeScript + Workers Static Assets + một D1. Ưu tiên chuyển nhanh, ít thành phần vận hành, tận dụng Free và giữ đúng nghiệp vụ.

- Giữ HTML/CSS/JavaScript thuần, không viết lại frontend bằng React hoặc thêm SSR.
- URL public mới là `https://hosting.pdl.vn/brand.json`; cấu trúc JSON giữ nguyên để MU-plugin đổi endpoint mà không đổi parser.
- Admin mới là `https://hosting.pdl.vn/`; API mới là `https://hosting.pdl.vn/api/*`.
- URL cũ `https://app.pdl.vn/ql-hosting/brand.json` cần được redirect/alias trong giai đoạn chuyển tiếp hoặc cập nhật toàn bộ consumer trước cutover.
- Thêm Cloudflare Turnstile cho login. Site key public: `0x4AAAAAAElwX3Sma_XFYZb4`; `TURNSTILE_SECRET_KEY` tạo bằng Worker Secret sau, không commit vào repo.
- Cron hằng ngày nằm trong cùng Worker. Giai đoạn đầu không thêm KV, R2, Durable Objects hoặc Queues.
- Tạo thư mục `ql-hosting-worker/` riêng, giữ PHP để đối chiếu/rollback và giữ các thay đổi có sẵn trong checkout.
- Không thay đổi module WordPress khác. Đây là chuyển ứng dụng quản trị, không di chuyển website/hosting khách hàng sang Workers.

### Hiện trạng đã kiểm tra

| Thành phần local | Kết quả |
|---|---|
| `ql-hosting/brand.json` | 8.878 byte, 26 domain, 2 liên hệ |
| `brand.json` ở gốc repo | 6.901 byte; nội dung khác bản trong ứng dụng |
| CSS + JS + logo quản trị | 58.607 byte |
| `ql-hosting/assets/app.js` | Gọi `api.php?action=...`; nút Lưu hiện lưu cả brand và settings |
| `pdl-modules/brand-widget.php` | Gọi URL public cũ kèm `?_=<mốc giờ>` |
| PHP storage | Backup brand trước khi lưu; settings chứa cả tài khoản, Telegram và lịch gửi |

Đây không phải số liệu production. Trước import phải kiểm tra `config.php` thật vì `brand_file` có thể được override; không mặc định chọn file ở gốc repo.

## 2. Kiến trúc và lưu trữ

```text
Quản trị ── HTML/CSS/JS/logo ── Workers Static Assets
         └─ /api/* ── Hono + xác thực ── D1
Widget ── /brand.json ── Cache API ── D1 khi cache miss
Scheduled event ── cùng Worker ── D1 nhận quyền gửi ── Telegram
```

Chọn D1 để có cập nhật có điều kiện, khóa duy nhất và giao dịch batch. KV có eventual consistency và thiếu giao dịch nguyên tử, làm phức tạp lưu đồng thời và chống gửi trùng. Với dữ liệu hiện tại, dung lượng không phải nút thắt. [D1 batch][d1-api], [KV consistency][kv-consistency].

| Bảng | Dữ liệu và ràng buộc |
|---|---|
| `documents` | `key TEXT PRIMARY KEY`, `json TEXT`, `version INTEGER`, `updated_at`; hai key `brand`, `settings` |
| `auth_state` | Singleton `id = 1`, username, password record có thuật toán/salt/tham số, `auth_version` |
| `notification_log` | Khóa duy nhất domain/ngày hết hạn/mốc nhắc; last_sent_at, trạng thái, claim token, lease deadline, lỗi đã lọc bí mật |
| `brand_revisions` | JSON trước khi lưu, version và thời gian; giữ tối đa 30 bản gần nhất |

Quy tắc triển khai:

1. `documents.settings` chỉ chứa cấu hình ứng dụng; không chứa password hoặc notification log. Không ghi đè auth bằng payload frontend.
2. Giữ brand thành một JSON nhỏ. `domains` là object đánh khóa bằng domain, không đổi thành array. Giữ các trường/kiểu mà widget dùng: company, address, website, logo, updated_at, notify, contacts, domains, expire, hosting_note và notify từng domain.
3. Version và dữ liệu nội bộ chỉ nằm trong API quản trị, không thêm vào JSON public. Đọc document bằng primary key; chưa tách mỗi domain thành bảng riêng.
4. Dùng SQL bind parameters. Lưu bằng điều kiện `WHERE version = ?`; conflict trả `409`, không âm thầm ghi đè dữ liệu từ tab khác.
5. Backup và cập nhật brand cùng giao dịch. UPDATE không khớp version không tự làm batch thất bại: statement tạo revision cũng phải có điều kiện version tương ứng; kiểm tra số hàng thay đổi.
6. Giữ UI đổi Telegram token: mã hóa AES-GCM trước khi lưu settings, nonce mới mỗi lần mã hóa; khóa mã hóa riêng đặt trong Worker Secrets. Không trả token thô về UI/log/export thông thường.
7. Giới hạn toàn request ban đầu 256 KiB, trả `413` khi vượt; kiểm tra ngưỡng với dữ liệu import. Chỉ tạo index phục vụ truy vấn thực tế, đo cả chi phí index qua `rows_read`/`rows_written`.

## 3. Project, Static Assets và routing

```text
ql-hosting-worker/
├── public/
│   ├── index.html
│   └── assets/                 # app.css, app.js, pdl-logo.png
├── src/
│   ├── index.ts
│   ├── middleware/auth.ts
│   ├── routes/                 # auth, data, telegram
│   └── services/               # storage, normalize, crypto, reminders
├── migrations/
├── scripts/                    # import/export có kiểm tra
├── tests/
├── package.json
├── wrangler.toml
└── README.md
```

Cấu hình production hiện tại dùng D1 `qlhosting` và custom domain `hosting.pdl.vn`. Cron chưa bật để tránh gửi trước cutover:

```toml
name = "ql-hosting-worker"
main = "src/index.ts"
compatibility_date = "2026-09-05"

[[routes]]
pattern = "hosting.pdl.vn"
custom_domain = true

[assets]
directory = "./public"
binding = "ASSETS"
run_worker_first = ["/api/*", "/brand.json", "/ql-hosting", "/ql-hosting/*"]

[[d1_databases]]
binding = "DB"
database_name = "qlhosting"
database_id = "ebe514c4-51c5-4a2b-abff-5a18e35982f2"
migrations_dir = "migrations"

# Chỉ bật sau khi tắt cron PHP và kiểm tra cutover.
# [triggers]
# crons = ["0 0 * * *"]
```

- Dùng Wrangler hiện hành với Node runtime được hỗ trợ; khóa dependency trong lockfile, sinh binding types. Đọc skill Wrangler trước khi thực thi lệnh Wrangler.
- Dùng `[assets]`, không dùng `[site] bucket` hay đưa tất cả asset qua `hono/serve-static`. Không đặt `run_worker_first = true` toàn site. Chỉ API/JSON động vào Worker; asset đi trực tiếp Static Assets. [Routing][assets-routing], [Billing][assets-billing].
- Mount API tại `/api/*`, đặt `window.QLH_BASE = ''`, giữ static file ở `/assets/*`.
- Không cần SPA fallback cho giao diện một trang hiện tại; API sai path phải trả JSON `404`, không trả index.html.
- HTML chỉ là vỏ giao diện, không nhúng dữ liệu riêng. Không đặt brand.json thật, settings, PHP, backup hoặc secret trong `public/`.
- Kiểm tra DNS proxy và route `hosting.pdl.vn/*` trước deploy. Domain mới được dành riêng cho app này nên có thể phục vụ admin ở `/`; không chiếm `app.pdl.vn`.
- Trên `hosting.pdl.vn`, request `/ql-hosting` và `/ql-hosting/*` chỉ redirect về path root tương ứng để giảm nhầm lẫn sau chuyển đổi. Với `app.pdl.vn/ql-hosting`, cần rule redirect riêng hoặc cập nhật consumer trước cutover.
- Logo quản trị giữ trong Static Assets. Có thể giữ `brand.logo` cũ; nếu đổi sang ảnh ở Worker, dùng URL HTTPS tuyệt đối để widget trên domain khác tải đúng.
- Thay PHP `filemtime()` bằng version/hash asset khi build. Không cache vô hạn một tên file có nội dung thay đổi.

## 4. Authentication

Giữ username/password, Turnstile và JWT trong HttpOnly Cookie. Phải đo runtime trước khi chốt khả năng chạy hoàn toàn Free.

1. **Tài khoản cố định:** username là `phudigital` từ `ADMIN_USERNAME` trong Worker vars; mật khẩu là `ADMIN_PASSWORD` trong Worker Secret. Worker xác thực trực tiếp với secret, không nhận mật khẩu từ D1 hoặc frontend settings.
2. **Turnstile:** login gửi token từ widget site key `0x4AAAAAAElwX3Sma_XFYZb4`; Worker xác minh server-side bằng `TURNSTILE_SECRET_KEY` tại Siteverify. Nếu secret chưa cấu hình, login phải báo lỗi cấu hình, không bỏ qua kiểm tra. [Turnstile verify][turnstile-verify].
3. **Auth state:** D1 chỉ giữ username chuẩn hóa, password record PBKDF2 để giữ `auth_version`/thu hồi phiên; lần login hợp lệ đầu tiên tự tạo singleton từ `ADMIN_PASSWORD`. Không có endpoint setup hoặc bootstrap secret.
4. **Password KDF:** hash PBKDF2 chỉ dùng để tạo auth record/khôi phục phiên; nguồn xác thực là Worker Secret. Không dùng SHA-256 đơn thuần. [Web Crypto][web-crypto].
5. **Cookie/JWT:** Secure, HttpOnly, SameSite=Strict, path `/`, tối đa 8 giờ. Khóa thuật toán verify, kiểm tra expiry/issuer/audience `hosting.pdl.vn`. Signing secret khác encryption secret.
6. **Thu hồi phiên:** JWT có auth_version; API riêng đọc auth singleton để kiểm tra. Logout tăng auth_version. Đổi mật khẩu thực hiện bằng cách cập nhật `ADMIN_PASSWORD` Worker Secret, sau đó đăng nhập lại; không đổi từ UI.
7. **Request:** mutation chỉ POST; kiểm tra Origin theo allowlist quản trị `https://hosting.pdl.vn`, JSON Content-Type, giới hạn body. Rate limit login bằng cơ chế Cloudflare phù hợp đã xác minh lúc triển khai.
8. **Response:** auth/admin dùng `Cache-Control: no-store`, không CORS `*`. Không log password, cookie/JWT, bot token hoặc URL Telegram chứa token.

## 5. API và frontend

Các path dưới đây nằm tại root domain `hosting.pdl.vn`. Đổi helper `api()` từ `api.php?action=...` sang `/api/<action>` và kiểm tra mọi caller.

| Method/path | Hợp đồng |
|---|---|
| `GET /api/status` | `{ok, authenticated}`; không có trạng thái setup |
| `POST /api/login` | `{username, password}`; set cookie khi thành công |
| `POST /api/logout` | Thu hồi phiên và xóa cookie |
| `GET /api/data` | `{ok, brand, settings, brand_version, settings_version}`; settings lọc bí mật |
| `POST /api/save-brand` | `{brand, version}` → `{ok, brand, version, message}` |
| `POST /api/save-settings` | `{settings, version}` → settings công khai và version mới; allowlist fields |
| `POST /api/test-telegram` | Có auth; gửi test chủ động, kiểm tra HTTP status và Telegram ok |
| `POST /api/run-reminders` | Có auth; `{dry_run: true/false}` → `{ok, sent, skipped}` và lỗi nếu có |
| `GET /brand.json` | Public; JSON brand thuần, Content-Type JSON, CORS `*`; cache edge |

- Port normalization PHP: ngày dd/mm/yyyy → yyyy-mm-dd, domain trim/lowercase, notify toàn cục/từng domain, contact tùy chọn. Test object rỗng, ngày sai và Unicode.
- Telegram token trống nghĩa là giữ nguyên. UI chỉ nhận mask/trạng thái đã cấu hình. Tài khoản `phudigital` và mật khẩu do Worker Secret quản lý; UI chỉ cập nhật settings ứng dụng.
- Bỏ phụ thuộc `brand_file.split(...)`, `brand_writable` và cảnh báo quyền ghi file; thay bằng trạng thái lưu D1.
- Bỏ URL `cron.php?key=...`, thay bằng giờ chạy/timezone và kết quả lần chạy gần nhất. Không giữ endpoint cron GET gửi tin.
- Dirty tracking riêng brand/settings; chỉ lưu phần thay đổi. Không autosave từng phím hoặc polling nền liên tục.
- Test/dry-run dùng cấu hình đã lưu; nếu còn thay đổi, UI diễn đạt rõ. Không gọi save-settings vô điều kiện. Dry-run không gửi/ghi lịch sử.
- Nếu brand lưu thành công nhưng settings thất bại, báo kết quả từng phần; không đánh dấu toàn bộ đã lưu.

## 6. Cache và quota Free

### Chính sách cache

- Chỉ cache GET brand.json thành công và hoàn toàn public. Không cache lỗi DB, response có cookie hoặc API riêng.
- Cache API edge TTL 300 giây; browser max-age 60 giây. Tách header của bản cache edge và response browser. ETag có thể dùng version nhưng không query D1 kiểm tra version trên mỗi cache hit.
- Chuẩn hóa cache key về URL JSON không có query: endpoint này không có tham số chọn nội dung. Tham số `_` của widget không tạo thêm key. Không áp dụng bỏ query cho API khác.
- Cache miss mới đọc D1; hit không đọc D1/auth. Admin thấy dữ liệu mới từ response save và API riêng; public có thể chậm theo TTL edge cộng browser, khoảng 6 phút.
- `cache.delete()` chỉ tác dụng tại data center hiện tại. Không hứa invalidation toàn cầu tức thời; đo độ trễ thực tế. Cache không phải storage bền vững. [Cache API][cache-api].
- Cache API giảm DB reads/CPU, không loại bỏ request đã vào Worker. Static Assets trực tiếp tránh invocation xử lý asset. KV cacheTtl không miễn số KV get tính quota.
- Không publish lại Static Assets mỗi lần lưu brand: deployment assets không phải filesystem runtime có thể ghi trực tiếp.

### Hạn mức và dự toán

Đối chiếu tài liệu ngày 05/09/2026, kiểm tra lại khi deploy. Quota dịch vụ trên tài khoản còn được dùng bởi các project khác.

| Dịch vụ | Free |
|---|---|
| Workers động | 100.000 request/ngày; 10 ms CPU/invocation |
| Static Assets trực tiếp | Request miễn phí, không giới hạn; không tính thêm phí lưu assets |
| D1 | 5 triệu rows read/ngày, 100.000 rows written/ngày; 5 GB tổng, 500 MB/database |
| KV, để đối chiếu | 100.000 reads/ngày, 1.000 writes/ngày, 1 GB; không dùng trong phương án này |

Nguồn: [Workers][workers-pricing], [Assets][assets-billing], [D1 pricing][d1-pricing], [D1 limits][d1-limits], [KV pricing][kv-pricing]. D1 vượt hạn mức Free trả lỗi, không tự miễn phần vượt quota.

Ví dụ giả định mỗi website phát sinh 20 request widget tới Worker/ngày:

| Website hoạt động | Public request/ngày | % quota Workers |
|---|---:|---:|
| 26 | 520 | 0,52% |
| 1.000 | 20.000 | 20% |

Chưa gồm admin API, bot/retry và tải khác trên tài khoản. 26 domain trong file không chứng minh có đúng 26 website hoạt động.

- Worker requests = public request thực sự tới Worker + admin API + request động khác; không suy ra từ số domain đơn thuần.
- D1 reads = brand cache miss + auth/admin + cron/import/maintenance. Đọc theo khóa chính; xác minh bằng meta, không quét SQL danh sách domain trên mỗi GET public.
- D1 writes = dữ liệu thay đổi + revision/prune + auth thay đổi + claim/kết quả gửi + maintenance; index cũng có chi phí. Batch giảm round trip, không miễn rows.
- Không suy ra cache miss tối đa cố định từ TTL: cache riêng theo data center, có eviction và request đồng thời. Đo hit ratio thực tế.
- Giữ 30 revision. Dọn notification không còn liên quan domain/expiry hiện hành sau 90 ngày; không xóa marker còn cần cho dedupe hoặc repeat_after_days.
- Không ghi D1 cho mỗi GET public. Dùng metrics nền tảng, sampling log chẩn đoán và log lỗi/cron tổng hợp đã lọc bí mật.
- Đo CPU p50/p95/max, request/ngày, cache hit, rows_read/rows_written, dung lượng DB và quota còn lại toàn tài khoản. Ngưỡng rà soát nội bộ đề xuất 70%; chưa có số đo thì không kết luận đã tối ưu production.

## 7. Cron và Telegram

- Scheduled handler trong cùng Worker. Lịch dự kiến `0 0 * * *` = **07:00 Asia/Ho_Chi_Minh**; nếu chọn 08:00 dùng `0 1 * * *`. Cloudflare Cron dùng UTC. Staging không bật lịch gửi thật. [Cron][cron-docs].
- Tính days left theo ngày lịch Việt Nam, không dựa timezone mặc định Worker/browser. Dùng scheduled event time xác định ngày cho lần chạy lịch; test sát nửa đêm/cuối tháng/năm.
- Giữ reminders.days, notify_overdue, repeat_after_days, Telegram enabled và bỏ ngày sai. Fingerprint `domain|expire|milestone`, quá hạn dùng milestone `overdue`, đối chiếu last_sent_at với khoảng lặp.
- Import lịch gửi PHP trước khi bật cron. Không ghi lại toàn bộ settings sau mỗi lần chạy.
- Dry-run chỉ đọc/trả dự kiến: không claim, không gửi, không ghi lịch sử hoặc kết quả lần chạy thật.
- Nhận quyền gửi bằng INSERT/UPDATE D1 có điều kiện, kiểm tra số hàng thay đổi; cron/manual dùng chung claim token và lease. Lease bao phủ thời gian request có timeout; completion chỉ bởi chủ claim hợp lệ. Không tự chiếm claim khi request cũ còn chạy.
- Chỉ ghi last_sent_at sau Telegram xác nhận. Lỗi rõ ràng có retry giới hạn và xử lý 429/retry_after. Timeout/crash sau gửi là kết quả không chắc chắn: lưu trạng thái cần đối chiếu, không tự retry vô hạn. D1 và Telegram không có transaction chung; không cam kết exactly-once.
- Ban đầu giữ tin theo domain như PHP. Giới hạn số tin/invocation theo ngân sách subrequest/CPU thực đo, chừa phần DB/retry; không gửi vòng lặp vô hạn. Free có giới hạn 50 external subrequests/invocation và giới hạn truy vấn D1 riêng; đối chiếu khi triển khai. [Workers limits][workers-limits], [D1 limits][d1-limits].
- Vượt batch phải giữ trạng thái còn chờ và báo cần xử lý tiếp; không bỏ qua hoặc báo gửi hết. Chỉ cutover khi batch đủ tải dự kiến; nếu không, thiết kế lịch xử lý tiếp có giới hạn hoặc digest và test trước production.
- Giữ nội dung tin, escape HTML khi dùng parse mode, chia nội dung theo giới hạn Telegram hiện hành; kiểm tra HTTP status và body ok. Không trả lỗi chứa token cho UI.
- Ghi tổng hợp lần chạy thật: thời điểm, thành công/lỗi/còn chờ. `ctx.waitUntil()` không thay hàng đợi bền vững hoặc retry bảo đảm.

## 8. Trình tự thực hiện

### A. Chụp hiện trạng

1. Kiểm tra instructions/git status; xác định brand/settings/config production thực tế.
2. Export brand/settings/notification log gốc; cất bản có bí mật riêng, không commit hoặc đưa vào Static Assets.
3. Ghi DNS/routes, cron PHP, hostname quản trị, lượng request; kiểm tra plan/quota D1/Workers/cron còn lại.
4. Tạo Turnstile widget cho `hosting.pdl.vn` bằng site key đã chọn, thêm `TURNSTILE_SECRET_KEY` và `ADMIN_PASSWORD` vào Worker Secrets khi có secret thật.
5. Tạo staging Worker/D1 và secrets riêng, cron tắt. Chốt hash/reset, giờ cron và redirect/cập nhật consumer từ URL cũ trước cutover.

### B. Chuyển mã

1. Viết migrations, import có validate/dry-run/transaction; mặc định từ chối ghi đè DB có dữ liệu. Không biến import lỗi thành dữ liệu rỗng.
2. Port normalize/auth/storage; kiểm tra đăng nhập bằng Worker Secret và version conflict.
3. Chuyển HTML, API helper/contract, dirty tracking, cron UI, kết quả lưu từng phần.
4. Thêm public cache, reminders, export/restore có auth. Restore brand tạo version mới và backup trạng thái hiện tại, không ghi đè auth/settings.

### C. Nghiệm thu staging

- Typecheck, tests nghiệp vụ, kiểm tra bundle/config bằng Wrangler phù hợp; chạy dev thôi chưa chứng minh production.
- So sánh JSON trước/sau import theo cấu trúc/giá trị; đủ domain/contact/notify/ngày, không có bí mật public.
- Browser: login/logout, cập nhật `ADMIN_PASSWORD` qua Wrangler, lưu/tải lại, conflict hai tab, lịch/preview/logo/mobile, lỗi lưu từng phần.
- Consumer WordPress: JSON staging, query `_`, CORS, cache hit/miss, độ trễ sau save, không cache lỗi DB.
- Auth: unauth/CSRF, sai route, body lớn, session hết hạn/thu hồi, secret thiếu hoặc sai phải từ chối đăng nhập.
- Reminder: mốc 30/14/7/3/1/0, quá hạn/khoảng lặp, đổi expiry, Telegram tắt, dry-run không side effect, manual trùng cron, timeout/retry và vượt batch.
- Test Telegram thật vào chat thử nghiệm đã chỉ định cho triển khai, xác nhận tin và đọc lại log. Không dùng cron staging gửi tới khách hàng.
- Đo CPU auth/cron, D1 rows và request assets trên Cloudflare thực. Kiểm tra quota còn lại trước xác nhận chạy được Free.
- Thử restore/export và rollback staging. D1 Free Time Travel 7 ngày hỗ trợ bổ sung, không thay export trước migration. [D1 limits][d1-limits].

### D. Cutover

1. Chọn thời điểm bảo trì, khóa ghi PHP và tắt cron PHP; giữ JSON cũ phục vụ trong lúc chuẩn bị.
2. Export cuối sau khóa ghi/tắt cron; import/đối chiếu brand/settings/log và xác nhận credential sẵn sàng.
3. Deploy production cron còn tắt, bật routing đúng phạm vi; kiểm tra `https://hosting.pdl.vn/`, `https://hosting.pdl.vn/brand.json`, API admin và redirect/cập nhật từ URL cũ.
4. Chạy dry-run/đối chiếu danh sách; chỉ bật cron Worker khi PHP cron đã tắt và batch đủ tải.
5. Ghi version/deployment, thời điểm, export phục hồi và thao tác rollback trong README. Giữ PHP không ghi/không cron ít nhất 7 ngày.
6. Kiểm tra lần cron đầu và metrics sau 24–72 giờ. Nếu cần tự động theo dõi ngoài phiên làm việc, thiết lập riêng theo yêu cầu; không hứa tác vụ nền chưa cấu hình.

### E. Đã thực thi ngày 05/09/2026

- D1 `qlhosting` (`ebe514c4-51c5-4a2b-abff-5a18e35982f2`) đã chạy migration `0001_initial.sql` và import brand/settings không chứa password hash hoặc Telegram token.
- Worker `ql-hosting-worker` đã deploy version `8758cd4b-0d99-415c-bb6d-1df9dfc18317` với custom domain `hosting.pdl.vn`; Static Assets upload 4 file, bundle 98,13 KiB (gzip 24,31 KiB).
- Bản Worker auth cố định tài khoản `phudigital` ban đầu deploy với version `e5d0fdf0-6e0a-4f19-a1b5-3712372a098a`. Cả `JWT_SECRET`, `SETTINGS_ENCRYPTION_KEY`, `TURNSTILE_SECRET_KEY` và `ADMIN_PASSWORD` đã được lưu. Sự cố đăng nhập do version chứa `ADMIN_PASSWORD` mới chỉ được lưu; đã deploy version `6784411b-2cb8-40f6-91e0-f6a04aed554b` ở 100% traffic để kích hoạt.
- Đã kiểm tra widget Turnstile cho phép `pdl.vn`; trình duyệt tại `hosting.pdl.vn` nhận token thành công và server chuyển qua bước kiểm tra mật khẩu. D1 đọc được brand/settings hợp lệ. Bản sửa bổ sung thông báo ngay trong form, xử lý token hết hạn/sai hostname/lỗi dịch vụ và cookie hỏng; test login→dashboard→logout dùng Worker/D1 cục bộ.
- Bản sửa đã deploy version `69744bd8-8f2f-41aa-aacd-35595d09bf84`, có đủ 4 Secrets; 19 test cục bộ đạt. Production đã ghi nhận auth singleton `phudigital`; token sai trả `400`, cookie hỏng được xử lý như chưa đăng nhập.
- Kiểm tra live: `/` trả HTML Turnstile, `/brand.json` trả JSON public có cache headers, `/api/status` trả đúng site key, `/ql-hosting` redirect 308 về `/`.

### Rollback

- Tắt cron Worker trước bật lại cron PHP.
- Nếu Worker đã nhận dữ liệu mới, export brand/settings/lịch gửi mới và chuyển về định dạng PHP trước đổi route; không phục hồi mù bản cũ gây mất cập nhật/gửi lặp.
- Chuyển bí mật bằng quy trình riêng. Password record mới có thể không tương thích PHP: chuẩn bị phục hồi/reset và xác nhận đăng nhập, không copy hash mù.
- Khôi phục route, kiểm tra JSON/admin, rồi mở ghi và cron PHP khi dữ liệu đồng bộ. Giữ nguyên D1 để đối chiếu sự cố.

## 9. Đầu ra khi triển khai

- Mã Worker/frontend giữ chức năng, migrations, import/export, tests liên quan.
- README secrets/routing/cron/cache/quota/backup/restore/rollback khớp hành vi thực.
- Báo cáo deployment/version, tương thích URL/JSON, CPU, D1 rows, dung lượng/quota; phân biệt đo thật và giả định.
- Chưa ghi hoàn tất migration nếu chưa kiểm chứng consumer, auth CPU, cutover dữ liệu và lịch nhắc. Ghi rõ phần còn chờ và việc tiếp theo.

## 10. Nguồn kỹ thuật

Đối chiếu ngày 05/09/2026. Khi thực thi, kiểm tra lại limits, CPU, Wrangler và routing có thể thay đổi.

[workers-pricing]: https://developers.cloudflare.com/workers/platform/pricing/
[workers-limits]: https://developers.cloudflare.com/workers/platform/limits/
[assets-billing]: https://developers.cloudflare.com/workers/static-assets/billing-and-limitations/
[assets-routing]: https://developers.cloudflare.com/workers/static-assets/routing/worker-script/
[d1-pricing]: https://developers.cloudflare.com/d1/platform/pricing/
[d1-limits]: https://developers.cloudflare.com/d1/platform/limits/
[d1-api]: https://developers.cloudflare.com/d1/worker-api/d1-database/
[kv-pricing]: https://developers.cloudflare.com/kv/platform/pricing/
[kv-consistency]: https://developers.cloudflare.com/kv/concepts/how-kv-works/
[cache-api]: https://developers.cloudflare.com/workers/runtime-apis/cache/
[cron-docs]: https://developers.cloudflare.com/workers/configuration/cron-triggers/
[web-crypto]: https://developers.cloudflare.com/workers/runtime-apis/web-crypto/
[turnstile-verify]: https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
