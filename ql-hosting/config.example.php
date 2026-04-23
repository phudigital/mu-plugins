<?php
return [
    // By default the app stores data in ql-hosting/brand.json.
    // If you want another location, set an absolute path here.
    // Example:
    // 'brand_file' => '/var/www/phudigital/data/www/app.pdl.vn/ql-hosting/brand.json',
    'brand_file' => __DIR__ . '/brand.json',

    // Keep app settings outside public_html if your hosting layout allows it.
    'settings_file' => __DIR__ . '/data/settings.json',
    'backup_dir' => __DIR__ . '/data/backups',
    'timezone' => 'Asia/Ho_Chi_Minh',
];
