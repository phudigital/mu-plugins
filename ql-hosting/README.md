# QL Hosting PDL

Ứng dụng PHP thuần để quản lý `brand.json` qua giao diện mobile-first tại `/ql-hosting`.

## Cấu trúc

```text
ql-hosting/
├── index.php
├── api.php
├── cron.php
├── lib.php
├── config.example.php
├── assets/
└── data/
```

Mặc định app đọc và ghi file:

```text
ql-hosting/brand.json
```

Nếu bạn vẫn muốn dùng file ở chỗ khác, copy `config.example.php` thành `config.php` rồi sửa `brand_file` thành đường dẫn tuyệt đối.

## Deploy

Upload nguyên thư mục `ql-hosting` vào public root của `app.pdl.vn`, ví dụ:

```text
public_html/
└── ql-hosting/
    ├── brand.json
    └── ...
```

Sau đó mở:

```text
https://app.pdl.vn/ql-hosting/
```

Tài khoản mặc định:

- tài khoản: `phudigital`
- mật khẩu: `PhuDigital68^*`

App vẫn đọc `data/settings.json` nếu file này có mặt, nhưng từ bản này trở đi ngay cả khi bạn quên upload file đó thì vẫn đăng nhập được bằng tài khoản mặc định ở trên. Không lưu mật khẩu dạng chữ trong app, chỉ lưu hash.

## Telegram

Trong tab Telegram:

- bật thông báo
- nhập Bot token
- nhập Chat ID
- bấm gửi thử
- bấm lưu

App sẽ hiển thị sẵn URL cron có key riêng. Tạo cron hằng ngày trong FastPanel gọi URL đó để tự gửi nhắc hạn.

## Quyền ghi file

PHP cần ghi được:

- `ql-hosting/brand.json`
- `ql-hosting/data/settings.json`
- `ql-hosting/data/backups/`

Mỗi lần lưu `brand.json`, app tự tạo một bản backup trong `data/backups`.
