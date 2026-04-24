# QL Hosting PDL

Ứng dụng PHP thuần để quản lý `brand.json`, lịch gia hạn domain/hosting và nhắc hạn Telegram tại `/ql-hosting`.

## Tính năng

- Cập nhật thông tin thương hiệu, liên hệ CSKH và domain trong `brand.json`.
- Preview widget WordPress giống giao diện đang hiển thị trong dashboard khách hàng.
- Nhập ngày theo định dạng `dd/mm/yyyy`, lưu nội bộ dạng `yyyy-mm-dd`.
- Nhắc hạn qua Telegram bằng cron hằng ngày.
- Lưu dữ liệu bằng JSON, không cần database.

## Cấu trúc

```text
ql-hosting/
├── index.php
├── api.php
├── cron.php
├── lib.php
├── brand.json
├── config.example.php
├── assets/
│   ├── app.css
│   ├── app.js
│   └── pdl-logo.png
└── data/
    └── .htaccess
```

## Bảo mật

Các file nhạy cảm không nên commit lên GitHub:

- `ql-hosting/config.php`
- `ql-hosting/data/settings.json`
- `ql-hosting/data/backups/`

Repo đã ignore các file trên. Trên server thật, `settings.json` sẽ chứa password hash, Telegram token, Chat ID, cron key và lịch gửi gần nhất.

Nếu deploy lần đầu chưa có `settings.json`, app sẽ chuyển sang màn hình tạo tài khoản quản trị đầu tiên.

## Deploy

Upload thư mục `ql-hosting` vào public root của `app.pdl.vn`:

```text
public_html/
└── ql-hosting/
```

Mở trang quản trị:

```text
https://app.pdl.vn/ql-hosting/
```

File public cho mu-plugin đọc:

```text
https://app.pdl.vn/ql-hosting/brand.json
```

## Cấu hình Nginx

`brand.json` cần public để mu-plugin đọc được. Các file còn lại trong `data/` cần chặn truy cập:

```nginx
location = /ql-hosting/brand.json {
    add_header Access-Control-Allow-Origin "*" always;
    try_files $uri =404;
}

location ^~ /ql-hosting/data/ {
    deny all;
}
```

## Cron Telegram

Sau khi cấu hình Telegram trong app, tab Bot sẽ hiển thị URL cron có key riêng. Tạo cron hằng ngày trong FastPanel gọi URL đó bằng `curl`.

Ví dụ format lệnh:

```bash
curl -fsS 'https://app.pdl.vn/ql-hosting/cron.php?key=YOUR_PRIVATE_CRON_KEY' >/dev/null 2>&1
```

Không đưa cron key thật vào README, issue, commit message hoặc ảnh chụp public.

## Quyền ghi file

PHP cần ghi được:

- `ql-hosting/brand.json`
- `ql-hosting/data/settings.json`
- `ql-hosting/data/backups/`

Mỗi lần lưu `brand.json`, app tự tạo một bản backup trong `data/backups/`.
