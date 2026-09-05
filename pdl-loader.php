<?php
/**
 * Plugin Name: PDL Solutions — Core Manager
 * Description: Bộ MU-plugin quản trị website khách hàng PDL: dashboard hỗ trợ từ QL Hosting, branding đăng nhập, đổi URL login, dọn admin, ẩn menu/plugin và sao chép nội dung.
 * Version: 1.4.1
 * Author: Công Ty TNHH Giải Pháp PDL
 * Author URI: https://pdl.vn
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PDL_MODULES_DIR', __DIR__ . '/pdl-modules/' );
define( 'PDL_VERSION', '1.4.1' );
define( 'PDL_MODULE_ERRORS_OPTION', 'pdl_core_manager_module_errors' );

if ( ! defined( 'PDL_HOSTING_MANAGER_URL' ) ) {
    define( 'PDL_HOSTING_MANAGER_URL', 'https://hosting.pdl.vn/' );
}

if ( ! defined( 'PDL_HOSTING_BRAND_JSON_URL' ) ) {
    define( 'PDL_HOSTING_BRAND_JSON_URL', 'https://hosting.pdl.vn/brand.json' );
}

function pdl_core_manager_hosting_url() {
    $url = esc_url_raw( trim( (string) apply_filters( 'pdl_core_manager_hosting_url', PDL_HOSTING_MANAGER_URL ) ) );

    return trailingslashit( $url ?: PDL_HOSTING_MANAGER_URL );
}

function pdl_core_manager_brand_json_url() {
    $url = esc_url_raw( trim( (string) apply_filters( 'pdl_core_manager_brand_json_url', PDL_HOSTING_BRAND_JSON_URL ) ) );

    return $url ?: PDL_HOSTING_BRAND_JSON_URL;
}

function pdl_core_manager_admin_notice( $message, $type = 'warning' ) {
    add_action(
        'admin_notices',
        function () use ( $message, $type ) {
            if ( current_user_can( 'manage_options' ) ) {
                printf(
                    '<div class="notice notice-%s pdl-admin-notice is-dismissible"><p>%s</p></div>',
                    esc_attr( $type ),
                    esc_html( $message )
                );
            }
        }
    );
}

function pdl_core_manager_record_module_error( $module, $message ) {
    $errors            = (array) get_option( PDL_MODULE_ERRORS_OPTION, array() );
    $errors[ $module ] = array(
        'message' => sanitize_text_field( $message ),
        'time'    => current_time( 'mysql' ),
    );

    update_option( PDL_MODULE_ERRORS_OPTION, $errors, false );
}

function pdl_core_manager_clear_module_error( $module ) {
    $errors = (array) get_option( PDL_MODULE_ERRORS_OPTION, array() );

    if ( isset( $errors[ $module ] ) ) {
        unset( $errors[ $module ] );
        update_option( PDL_MODULE_ERRORS_OPTION, $errors, false );
    }
}

function pdl_core_manager_show_module_error_notices() {
    $errors = (array) get_option( PDL_MODULE_ERRORS_OPTION, array() );

    foreach ( $errors as $module => $error ) {
        $message = isset( $error['message'] ) ? (string) $error['message'] : 'Không rõ lỗi.';
        $time    = isset( $error['time'] ) ? (string) $error['time'] : '';

        pdl_core_manager_admin_notice(
            sprintf(
                'PDL Core Manager đã tạm bỏ qua module "%s" vì gặp lỗi: %s%s',
                $module,
                $message,
                $time ? ' (' . $time . ')' : ''
            ),
            'error'
        );
    }
}
add_action( 'admin_init', 'pdl_core_manager_show_module_error_notices' );

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
            try {
                require_once $file;
                pdl_core_manager_clear_module_error( $module );
            } catch ( Throwable $exception ) {
                pdl_core_manager_record_module_error( $module, $exception->getMessage() );
                error_log( sprintf( 'PDL Core Manager skipped module %s: %s', $module, $exception->getMessage() ) );
            }
        }
    }
}
