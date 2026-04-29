<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function pdl_admin_utilities_settings() {
    return apply_filters(
        'pdl_admin_utilities_settings',
        [
            'admin_bar' => [
                'remove_wp_logo'     => true,
                'remove_site_name'   => false,
                'remove_customize'   => false,
                'remove_updates'     => false,
                'remove_comments'    => false,
                'remove_new_content' => true,
                'remove_howdy'       => true,
                'remove_help_tabs'   => true,
            ],
            'admin_notices' => [
                'hide'                    => true,
                'super_admin_only_updates' => true,
            ],
            'dashboard_widgets' => [
                'remove_welcome_panel' => true,
                'remove_quick_press'   => true,
                'remove_activity'      => false,
                'remove_pdl_widget'    => false,
                'remove_right_now'     => false,
                'remove_primary'       => true,
                'remove_site_health'   => true,
            ],
            'content_duplicate' => [
                'enabled'        => true,
                'redirect_after' => 'edit',
                'post_status'    => 'draft',
                'title_suffix'   => ' - Copy',
            ],
            'revisions' => [
                'max_revisions' => 3,
            ],
            'upload_limits' => [
                'images_only'    => true,
                'max_image_size' => 2 * 1024 * 1024,
            ],
        ]
    );
}

function pdl_admin_utilities_setting( $group, $key, $default = false ) {
    $settings = pdl_admin_utilities_settings();

    return $settings[ $group ][ $key ] ?? $default;
}

function pdl_admin_utilities_is_super_admin() {
    if ( function_exists( 'pdl_hidden_plugins_is_super_admin' ) ) {
        return pdl_hidden_plugins_is_super_admin();
    }

    if ( function_exists( 'is_user_logged_in' ) && ! is_user_logged_in() ) {
        return false;
    }

    if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'is_super_admin' ) ) {
        return is_super_admin();
    }

    return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
}

function pdl_admin_utilities_updates_super_admin_only() {
    return (bool) pdl_admin_utilities_setting( 'admin_notices', 'super_admin_only_updates', true );
}

function pdl_admin_utilities_cleanup_admin_bar( $wp_admin_bar ) {
    $map = [
        'remove_wp_logo'     => 'wp-logo',
        'remove_site_name'   => 'site-name',
        'remove_customize'   => 'customize',
        'remove_updates'     => 'updates',
        'remove_comments'    => 'comments',
        'remove_new_content' => 'new-content',
    ];

    foreach ( $map as $setting => $node_id ) {
        if ( pdl_admin_utilities_setting( 'admin_bar', $setting ) ) {
            $wp_admin_bar->remove_node( $node_id );
        }
    }

    if ( pdl_admin_utilities_updates_super_admin_only() && ! pdl_admin_utilities_is_super_admin() ) {
        $wp_admin_bar->remove_node( 'updates' );
    }
}
add_action( 'admin_bar_menu', 'pdl_admin_utilities_cleanup_admin_bar', 999 );

function pdl_admin_utilities_replace_howdy_text( $translated, $text, $domain = null ) {
    if ( ! pdl_admin_utilities_setting( 'admin_bar', 'remove_howdy' ) ) {
        return $translated;
    }

    if ( 'Howdy, %s' === $text || 'Xin chào, %s' === $translated || false !== strpos( (string) $translated, 'Xin chào,' ) ) {
        return '%s';
    }

    return $translated;
}
add_filter( 'gettext', 'pdl_admin_utilities_replace_howdy_text', 20, 3 );

function pdl_admin_utilities_remove_help_tabs() {
    if ( ! pdl_admin_utilities_setting( 'admin_bar', 'remove_help_tabs' ) || ! function_exists( 'get_current_screen' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( $screen && method_exists( $screen, 'remove_help_tabs' ) ) {
        $screen->remove_help_tabs();
    }
}
add_action( 'admin_head', 'pdl_admin_utilities_remove_help_tabs', 1 );

function pdl_admin_utilities_hide_admin_notices_styles() {
    if ( ! pdl_admin_utilities_setting( 'admin_notices', 'hide' ) ) {
        return;
    }

    if ( pdl_admin_utilities_updates_super_admin_only() && pdl_admin_utilities_is_super_admin() ) {
        return;
    }
    ?>
    <style>
        body.wp-admin .notice,
        body.wp-admin div.error,
        body.wp-admin div.updated,
        body.wp-admin .update-nag,
        body.wp-admin .notice.notice-warning,
        body.wp-admin .notice.notice-error,
        body.wp-admin .notice.notice-info,
        body.wp-admin .notice.notice-success,
        body.wp-admin #adminmenu .update-plugins,
        body.wp-admin #wp-admin-bar-updates {
            display: none !important;
        }
        body.wp-admin .notice.pdl-admin-notice {
            display: block !important;
        }
    </style>
    <?php
}
add_action( 'admin_head', 'pdl_admin_utilities_hide_admin_notices_styles', 999 );

function pdl_admin_utilities_remove_dashboard_widgets() {
    if ( pdl_admin_utilities_setting( 'dashboard_widgets', 'remove_welcome_panel' ) ) {
        remove_action( 'welcome_panel', 'wp_welcome_panel' );
    }

    $widgets = [
        'remove_quick_press' => [ 'dashboard_quick_press', 'dashboard', 'side' ],
        'remove_activity'    => [ 'dashboard_activity', 'dashboard', 'normal' ],
        'remove_pdl_widget'  => [ 'pdl_brand_widget', 'dashboard', 'normal' ],
        'remove_right_now'   => [ 'dashboard_right_now', 'dashboard', 'normal' ],
        'remove_primary'     => [ 'dashboard_primary', 'dashboard', 'side' ],
        'remove_site_health' => [ 'dashboard_site_health', 'dashboard', 'normal' ],
    ];

    foreach ( $widgets as $setting => $args ) {
        if ( pdl_admin_utilities_setting( 'dashboard_widgets', $setting ) ) {
            remove_meta_box( $args[0], $args[1], $args[2] );
        }
    }
}
add_action( 'wp_dashboard_setup', 'pdl_admin_utilities_remove_dashboard_widgets', 1000 );

function pdl_admin_utilities_duplicate_enabled_for_post_type( $post_type ) {
    if ( ! pdl_admin_utilities_setting( 'content_duplicate', 'enabled' ) ) {
        return false;
    }

    if ( ! post_type_exists( $post_type ) ) {
        return false;
    }

    $object = get_post_type_object( $post_type );

    return $object && ! empty( $object->public );
}

function pdl_admin_utilities_duplicate_post( $post_id ) {
    $post = get_post( $post_id );

    if ( ! $post ) {
        return 0;
    }

    $status = sanitize_text_field( pdl_admin_utilities_setting( 'content_duplicate', 'post_status', 'draft' ) );
    $suffix = (string) pdl_admin_utilities_setting( 'content_duplicate', 'title_suffix', ' - Copy' );

    $new_post = [
        'post_author'    => $post->post_author,
        'post_content'   => $post->post_content,
        'post_excerpt'   => $post->post_excerpt,
        'post_parent'    => $post->post_parent,
        'post_password'  => $post->post_password,
        'post_status'    => $status,
        'post_title'     => $post->post_title . $suffix,
        'post_type'      => $post->post_type,
        'comment_status' => $post->comment_status,
        'ping_status'    => $post->ping_status,
        'menu_order'     => $post->menu_order,
    ];

    $new_post_id = wp_insert_post( $new_post );

    if ( ! $new_post_id || is_wp_error( $new_post_id ) ) {
        return 0;
    }

    foreach ( get_post_custom( $post_id ) as $meta_key => $meta_values ) {
        foreach ( (array) $meta_values as $meta_value ) {
            add_post_meta( $new_post_id, $meta_key, maybe_unserialize( $meta_value ) );
        }
    }

    foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
        $terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            wp_set_object_terms( $new_post_id, $terms, $taxonomy );
        }
    }

    return (int) $new_post_id;
}

function pdl_admin_utilities_duplicate_url( $post_id ) {
    return wp_nonce_url(
        add_query_arg(
            [
                'action' => 'pdl_duplicate_content',
                'post'   => (int) $post_id,
            ],
            admin_url( 'admin.php' )
        ),
        'pdl_duplicate_content_' . (int) $post_id
    );
}

function pdl_admin_utilities_add_duplicate_row_action( $actions, $post ) {
    if (
        empty( $post->ID )
        || empty( $post->post_type )
        || ! pdl_admin_utilities_duplicate_enabled_for_post_type( $post->post_type )
        || ! current_user_can( 'edit_post', $post->ID )
    ) {
        return $actions;
    }

    $actions['pdl_duplicate_content'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url( pdl_admin_utilities_duplicate_url( $post->ID ) ),
        esc_html__( 'Sao chép', 'pdl-core' )
    );

    return $actions;
}
add_filter( 'post_row_actions', 'pdl_admin_utilities_add_duplicate_row_action', 10, 2 );
add_filter( 'page_row_actions', 'pdl_admin_utilities_add_duplicate_row_action', 10, 2 );

function pdl_admin_utilities_limit_revisions( $num, $post = null ) {
    $max_revisions = (int) pdl_admin_utilities_setting( 'revisions', 'max_revisions', 3 );

    if ( $max_revisions < 0 ) {
        return $num;
    }

    return $max_revisions;
}
add_filter( 'wp_revisions_to_keep', 'pdl_admin_utilities_limit_revisions', 20, 2 );

function pdl_admin_utilities_handle_duplicate_action() {
    $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
        wp_die( 'Bạn không có quyền sao chép nội dung này.' );
    }

    check_admin_referer( 'pdl_duplicate_content_' . $post_id );

    $post = get_post( $post_id );
    if ( ! $post || ! pdl_admin_utilities_duplicate_enabled_for_post_type( $post->post_type ) ) {
        wp_die( 'Không thể sao chép nội dung này.' );
    }

    $new_post_id = pdl_admin_utilities_duplicate_post( $post_id );
    if ( ! $new_post_id ) {
        wp_die( 'Không tạo được bản sao nội dung.' );
    }

    if ( 'list' === pdl_admin_utilities_setting( 'content_duplicate', 'redirect_after', 'edit' ) ) {
        wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
        exit;
    }

    wp_redirect( get_edit_post_link( $new_post_id, 'raw' ) );
    exit;
}
add_action( 'admin_action_pdl_duplicate_content', 'pdl_admin_utilities_handle_duplicate_action' );

function pdl_admin_utilities_upload_size_limit( $size ) {
    if ( ! pdl_admin_utilities_setting( 'upload_limits', 'images_only' ) ) {
        return $size;
    }

    $max_size = (int) pdl_admin_utilities_setting( 'upload_limits', 'max_image_size', 2 * 1024 * 1024 );

    if ( $max_size <= 0 ) {
        return $size;
    }

    return min( (int) $size, $max_size );
}
add_filter( 'upload_size_limit', 'pdl_admin_utilities_upload_size_limit', 999 );

function pdl_admin_utilities_max_image_size() {
    return (int) pdl_admin_utilities_setting( 'upload_limits', 'max_image_size', 2 * 1024 * 1024 );
}

function pdl_admin_utilities_upload_limit_label() {
    $max_size = pdl_admin_utilities_max_image_size();

    if ( $max_size <= 0 ) {
        return '';
    }

    $megabytes = $max_size / 1024 / 1024;
    $label     = ( floor( $megabytes ) === $megabytes ) ? (string) (int) $megabytes : rtrim( rtrim( number_format( $megabytes, 2, '.', '' ), '0' ), '.' );

    return $label . 'Mb';
}

function pdl_admin_utilities_upload_limit_message() {
    $limit = pdl_admin_utilities_upload_limit_label();

    if ( '' === $limit ) {
        return 'Website được thiết kế để tối ưu trải nghiệm người dùng, vui lòng giảm dung lượng ảnh trước khi upload.';
    }

    return sprintf(
        'Website được thiết kế để tối ưu trải nghiệm người dùng, chỉ cho phép upload ảnh tối đa %s, vui lòng giảm dung lượng ảnh trước khi upload.',
        $limit
    );
}

function pdl_admin_utilities_upload_size_error_message() {
    $limit = pdl_admin_utilities_upload_limit_label();

    if ( '' === $limit ) {
        return 'Tệp vượt quá kích thước tải lên tối đa cho trang web này. Vui lòng giảm dung lượng ảnh trước khi upload.';
    }

    return sprintf(
        'Tệp vượt quá kích thước tải lên tối đa cho trang web này (tối đa %s). Vui lòng giảm dung lượng ảnh trước khi upload.',
        $limit
    );
}

function pdl_admin_utilities_upload_limit_admin_script() {
    if ( ! pdl_admin_utilities_setting( 'upload_limits', 'images_only' ) || pdl_admin_utilities_max_image_size() <= 0 ) {
        return;
    }

    $message = pdl_admin_utilities_upload_size_error_message();
    ?>
    <script>
    (function(){
        var message = <?php echo wp_json_encode( $message ); ?>;
        if (window.pluploadL10n) {
            window.pluploadL10n.file_exceeds_size_limit = message;
            window.pluploadL10n.file_exceeds_upload_limit = message;
        }
        if (window._wpPluploadSettings && window._wpPluploadSettings.defaults) {
            window._wpPluploadSettings.defaults.multipart_params = window._wpPluploadSettings.defaults.multipart_params || {};
            window._wpPluploadSettings.defaults.multipart_params.pdl_upload_limit_message = message;
        }
        document.addEventListener('wp-uploader-error', function(event) {
            if (!event || !event.detail || !event.detail.message) {
                return;
            }
            if (event.detail.message.indexOf('kích thước tải lên tối đa') !== -1 || event.detail.message.indexOf('maximum upload size') !== -1) {
                event.detail.message = message;
            }
        });
    })();
    </script>
    <?php
}
add_action( 'admin_footer-upload.php', 'pdl_admin_utilities_upload_limit_admin_script', 20 );
add_action( 'admin_footer-media-new.php', 'pdl_admin_utilities_upload_limit_admin_script', 20 );

function pdl_admin_utilities_validate_image_upload( $file ) {
    if ( ! pdl_admin_utilities_setting( 'upload_limits', 'images_only' ) ) {
        return $file;
    }

    $mime_type = strtolower( (string) ( $file['type'] ?? '' ) );
    if ( 0 !== strpos( $mime_type, 'image/' ) ) {
        $file['error'] = 'PDL chỉ cho phép upload file hình ảnh.';
        return $file;
    }

    $max_size = pdl_admin_utilities_max_image_size();
    $size     = (int) ( $file['size'] ?? 0 );

    if ( $max_size > 0 && $size > $max_size ) {
        $file['error'] = pdl_admin_utilities_upload_limit_message();
    }

    return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'pdl_admin_utilities_validate_image_upload' );
