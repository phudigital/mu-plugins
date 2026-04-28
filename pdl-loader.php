<?php
/**
 * Plugin Name: PDL Solutions — Core Manager
 * Description: Bộ MU-plugin quản trị website khách hàng PDL: dashboard hỗ trợ, branding đăng nhập, đổi URL login, dọn admin, ẩn menu/plugin và sao chép nội dung.
 * Version: 1.3.3
 * Author: Công Ty TNHH Giải Pháp PDL
 * Author URI: https://pdl.vn
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PDL_MODULES_DIR', __DIR__ . '/pdl-modules/' );
define( 'PDL_VERSION', '1.3.3' );

/**
 * ==================================================
 *  BẬT / TẮT MODULE — chỉnh sửa tại đây
 * ==================================================
 */
$pdl_modules = [
    'brand-widget'   => true,   // Widget thông tin & hỗ trợ PDL
    'login-branding' => true,   // Tuỳ biến giao diện đăng nhập
    'hide-login'     => true,   // Đổi đường dẫn đăng nhập sang /dang-nhap/
    'admin-menu'      => true,   // Ẩn menu admin không cần thiết
    'hidden-plugins'  => true,   // Ẩn plugin khỏi danh sách Plugins
    'admin-utilities' => true,   // Dọn admin bar/dashboard và sao chép nội dung
    'gclid-logger'   => false,  // Log IP + GCLID Google Ads
    'click-fraud'    => false,  // Chặn click fraud
    'image-compress' => false,  // Tự nén ảnh khi upload
    'security'       => false,  // Bảo mật WordPress
];

foreach ( $pdl_modules as $module => $enabled ) {
    if ( $enabled ) {
        $file = PDL_MODULES_DIR . $module . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
}
