# PDL MU-Plugins — Sổ Tay Deploy VPS

Tài liệu này dùng để bạn đọc lại nhanh khi cần:
- dò các website WordPress trên VPS
- upload bộ `mu-plugin`
- cài hàng loạt vào tất cả site WordPress
- chạy thử trước khi chạy thật
- kiểm tra sau deploy
- rollback nếu cần

Repo hiện tại đang chứa:
- `pdl-loader.php`
- `pdl-modules/admin-menu.php`
- `pdl-modules/brand-widget.php`
- `pdl-modules/hide-login.php`
- `pdl-modules/login-branding.php`
- `brand.json`
- `scripts/deploy-mu-plugins-from-folder.sh`
- `scripts/deploy-mu-plugins-from-archive.sh`

## Mục Tiêu

Deploy `mu-plugin` PDL vào tất cả website WordPress trên VPS FastPanel mà không đụng các website không phải WordPress.

Script deploy hiện tại chỉ nhận site nào có `wp-config.php`, nên:
- WordPress: có xử lý
- HTML tĩnh / Laravel / Node / PHP custom: bỏ qua

## Cấu Trúc MU-Plugin

Trên mỗi website WordPress, bộ plugin sẽ nằm ở:

```text
wp-content/mu-plugins/
├── pdl-loader.php
└── pdl-modules/
    ├── admin-menu.php
    ├── brand-widget.php
    ├── hide-login.php
    └── login-branding.php
```

## Cách Triển Khai Khuyên Dùng

Khuyên dùng cách `copy từ thư mục nguồn`, không dùng `.zip`.

Lý do:
- đơn giản nhất
- ít lỗi nhất
- không phụ thuộc `zip` / `unzip`
- dễ cập nhật: chỉ thay file trong thư mục nguồn rồi chạy lại script

## Chuẩn Bị Trên VPS

Đăng nhập `root`, rồi tạo 2 thư mục:

```bash
mkdir -p /root/pdl-mu-source/pdl-modules
mkdir -p /root/pdl-deploy
```

Upload các file sau lên VPS:

```text
/root/pdl-mu-source/
├── pdl-loader.php
└── pdl-modules/
    ├── admin-menu.php
    ├── brand-widget.php
    ├── hide-login.php
    └── login-branding.php
```

Upload script:

```text
/root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

Cấp quyền chạy:

```bash
chmod +x /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

## Detect VPS Chỉ Đọc WordPress

Dùng lệnh này để liệt kê tất cả website WordPress trên VPS:

```bash
find /var/www /home /srv /opt -type f -name wp-config.php 2>/dev/null | sort -u | while read -r cfg; do
  root="$(dirname "$cfg")"
  wp_content="$root/wp-content"
  mu_dir="$wp_content/mu-plugins"

  echo "---"
  echo "WP_CONFIG=$cfg"
  echo "SITE_ROOT=$root"
  echo "ROOT_OWNER=$(stat -c '%U:%G' "$root" 2>/dev/null || echo UNKNOWN)"
  echo "WP_CONTENT=$wp_content"

  if [ -d "$mu_dir" ]; then
    echo "MU_DIR=$mu_dir"
    echo "MU_EXISTS=yes"
    find "$mu_dir" -maxdepth 2 -mindepth 1 2>/dev/null | sort | sed 's/^/  /'
  else
    echo "MU_DIR=$mu_dir"
    echo "MU_EXISTS=no"
  fi
done
```

Mục đích:
- biết VPS có bao nhiêu site WordPress
- biết site nào đã có `mu-plugins`
- biết owner/group để script set permission đúng

## Script Deploy Từ Thư Mục

File script chính:

```text
scripts/deploy-mu-plugins-from-folder.sh
```

Script này sẽ:
- quét tất cả WordPress trong `/var/www`
- tạo `wp-content/mu-plugins` nếu chưa có
- backup `pdl-loader.php` cũ nếu có
- backup `pdl-modules` cũ nếu có
- ghi đè phần PDL mới
- không xóa các file mu-plugin khác không thuộc PDL
- set owner/group đúng theo từng website

### Chạy thử trước

```bash
DRY_RUN=1 SOURCE_DIR=/root/pdl-mu-source /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

Ý nghĩa:
- chỉ in ra lệnh sẽ chạy
- chưa ghi file thật
- dùng để kiểm tra xem script có chạm đúng các site WordPress không

### Chạy thật

```bash
SOURCE_DIR=/root/pdl-mu-source /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

### Nếu muốn đổi root tìm kiếm

Mặc định script tìm trong `/var/www`.

Nếu VPS của bạn có site ở nhiều nơi hơn:

```bash
DRY_RUN=1 SOURCE_DIR=/root/pdl-mu-source SEARCH_ROOTS="/var/www /home /srv /opt" /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

## Hành Vi Ghi Đè

Script `copy` hiện tại:
- có ghi đè `pdl-loader.php`
- có ghi đè toàn bộ thư mục `pdl-modules`
- không xóa toàn bộ `mu-plugins`
- không đụng các mu-plugin khác

Ví dụ:
- `installatron_hide_status_test.php` sẽ được giữ nguyên

## Thư Mục Backup

Mỗi lần chạy thật, backup nằm ở:

```text
/root/pdl-mu-backups/<timestamp>/<site_slug>/
```

Ví dụ:

```text
/root/pdl-mu-backups/20260420-120000/var_www_phudigital_data_www_pdl.vn/
```

## Kiểm Tra Sau Deploy

### 1. Kiểm tra file đã có trên từng site

```bash
find /var/www -path '*/wp-content/mu-plugins/pdl-loader.php' 2>/dev/null | sort
```

### 2. Kiểm tra thư mục modules

```bash
find /var/www -path '*/wp-content/mu-plugins/pdl-modules' -type d 2>/dev/null | sort
```

### 3. Kiểm tra nhanh một site cụ thể

Ví dụ với `pdl.vn`:

```bash
ls -la /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins
ls -la /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins/pdl-modules
```

### 4. Kiểm tra cú pháp PHP

```bash
php -l /root/pdl-mu-source/pdl-loader.php
php -l /root/pdl-mu-source/pdl-modules/admin-menu.php
php -l /root/pdl-mu-source/pdl-modules/brand-widget.php
php -l /root/pdl-mu-source/pdl-modules/hide-login.php
php -l /root/pdl-mu-source/pdl-modules/login-branding.php
```

### 5. Kiểm tra WordPress đã load mu-plugin

Nếu site có `wp` CLI chạy được:

```bash
wp plugin list --path=/var/www/phudigital/data/www/pdl.vn
```

Lưu ý:
- `mu-plugin` thường không hiện như plugin thường
- kiểm tra thực tế tốt nhất là vào Dashboard xem widget PDL xuất hiện

## Rollback Thủ Công

Nếu cần rollback một site, ví dụ `pdl.vn`:

```bash
cp -a /root/pdl-mu-backups/<timestamp>/var_www_phudigital_data_www_pdl.vn/pdl-loader.php /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins/pdl-loader.php
rm -rf /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins/pdl-modules
cp -a /root/pdl-mu-backups/<timestamp>/var_www_phudigital_data_www_pdl.vn/pdl-modules /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins/pdl-modules
chown phudigital:phudigital /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins/pdl-loader.php
chown -R phudigital:phudigital /var/www/phudigital/data/www/pdl.vn/wp-content/mu-plugins/pdl-modules
```

## Khi Dùng 2 VPS

Quy trình giống nhau:

1. upload source lên VPS đó
2. chạy detect WordPress
3. chạy `DRY_RUN=1`
4. đọc output
5. chạy thật
6. kiểm tra Dashboard

Nếu VPS vẫn là FastPanel và site ở dạng:

```text
/var/www/<user>/data/www/<domain>
```

thì script hiện tại dùng lại được nguyên.

## Cập Nhật `brand.json`

File:

```text
brand.json
```

Dùng để cấp dữ liệu cho widget `brand-widget.php`.

### Kiểm tra JSON hợp lệ

```bash
python3 -m json.tool brand.json >/dev/null && echo OK
```

### Các trường chính

```json
{
  "company": "Công Ty TNHH Giải Pháp PDL",
  "address": "Phường Bình Hòa, Tp. Hồ Chí Minh",
  "website": "https://pdl.vn",
  "logo": "https://pdl.vn/wp-content/uploads/2025/12/logopdlphudigital.png",
  "updated_at": "2025-04-20",
  "notify": {
    "active": true,
    "type": "info",
    "message": "Thông báo chung",
    "button_text": "Xem thêm",
    "button_url": "https://pdl.vn"
  }
}
```

### Mỗi domain

```json
"example.com": {
  "expire": "2026-11-16",
  "hosting_note": "",
  "notify": { "active": false, "type": "info", "message": "" }
}
```

Nếu chưa có ngày hết hạn thì để:

```json
"expire": ""
```

## Cách Tạo Script Trực Tiếp Trên VPS

Nếu không muốn upload file `.sh`, có thể tạo trực tiếp:

```bash
nano /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

Hoặc:

```bash
cat > /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

Sau đó:
- dán nội dung script
- nhấn `Ctrl + D`

Rồi cấp quyền:

```bash
chmod +x /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

## Bản Archive

Repo cũng có script:

```text
scripts/deploy-mu-plugins-from-archive.sh
```

Nhưng hiện tại không khuyên dùng, vì bạn đã gặp lỗi `.zip` từ macOS trên Linux.

Nếu cần dùng archive thì nên dùng `.tar.gz`, không dùng `.zip`.

## Checklist Mỗi Lần Deploy

```text
1. Sửa file local: pdl-loader.php / pdl-modules / brand.json
2. Kiểm tra cú pháp PHP
3. Upload source lên VPS: /root/pdl-mu-source
4. Detect WordPress trên VPS
5. Chạy DRY_RUN=1
6. Đọc output
7. Chạy thật
8. Kiểm tra file đã copy
9. Vào Dashboard vài site để xác nhận widget hoạt động
10. Nếu lỗi thì rollback từ /root/pdl-mu-backups
```

## Lệnh Dùng Nhanh

### Deploy thử

```bash
DRY_RUN=1 SOURCE_DIR=/root/pdl-mu-source /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

### Deploy thật

```bash
SOURCE_DIR=/root/pdl-mu-source /root/pdl-deploy/deploy-mu-plugins-from-folder.sh
```

### Kiểm tra site đã có `pdl-loader.php`

```bash
find /var/www -path '*/wp-content/mu-plugins/pdl-loader.php' 2>/dev/null | sort
```

### Kiểm tra JSON

```bash
python3 -m json.tool brand.json >/dev/null && echo OK
```

### Kiểm tra PHP

```bash
php -l pdl-loader.php
php -l pdl-modules/admin-menu.php
php -l pdl-modules/brand-widget.php
```
