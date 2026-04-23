<?php
/**
 * PDL Hide Login
 *
 * Đổi URL đăng nhập mặc định từ wp-login.php sang /dang-nhap/.
 * Cơ chế xử lý được rút gọn từ luồng chính của WPS Hide Login.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'PDL_HIDE_LOGIN_SLUG' ) ) {
    define( 'PDL_HIDE_LOGIN_SLUG', 'dang-nhap' );
}

if ( ! defined( 'PDL_HIDE_LOGIN_REDIRECT_SLUG' ) ) {
    define( 'PDL_HIDE_LOGIN_REDIRECT_SLUG', '404' );
}

$GLOBALS['pdl_hide_login_wp_login_php'] = false;

function pdl_hide_login_slug() {
    return sanitize_title_with_dashes( apply_filters( 'pdl_hide_login_slug', PDL_HIDE_LOGIN_SLUG ) );
}

function pdl_hide_login_redirect_slug() {
    return sanitize_title_with_dashes( apply_filters( 'pdl_hide_login_redirect_slug', PDL_HIDE_LOGIN_REDIRECT_SLUG ) );
}

function pdl_hide_login_use_trailing_slashes() {
    return '/' === substr( (string) get_option( 'permalink_structure' ), -1, 1 );
}

function pdl_hide_login_user_trailingslashit( $string ) {
    return pdl_hide_login_use_trailing_slashes() ? trailingslashit( $string ) : untrailingslashit( $string );
}

function pdl_hide_login_url( $scheme = null ) {
    $url = apply_filters( 'pdl_hide_login_home_url', home_url( '/', $scheme ) );

    if ( get_option( 'permalink_structure' ) ) {
        return pdl_hide_login_user_trailingslashit( $url . pdl_hide_login_slug() );
    }

    return $url . '?' . pdl_hide_login_slug();
}

function pdl_hide_login_redirect_url( $scheme = null ) {
    if ( get_option( 'permalink_structure' ) ) {
        return pdl_hide_login_user_trailingslashit( home_url( '/', $scheme ) . pdl_hide_login_redirect_slug() );
    }

    return home_url( '/', $scheme ) . '?' . pdl_hide_login_redirect_slug();
}

function pdl_hide_login_active_regular_plugins() {
    $plugins = (array) get_option( 'active_plugins', array() );

    if ( is_multisite() ) {
        $network_plugins = array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) );
        $plugins         = array_merge( $plugins, $network_plugins );
    }

    return array_unique( $plugins );
}

function pdl_hide_login_regular_plugin_is_active( $plugin_file ) {
    return in_array( $plugin_file, pdl_hide_login_active_regular_plugins(), true );
}

function pdl_hide_login_sync_wps_hide_login_options() {
    $login_slug    = pdl_hide_login_slug();
    $redirect_slug = pdl_hide_login_redirect_slug();

    if ( get_option( 'whl_page' ) !== $login_slug ) {
        update_option( 'whl_page', $login_slug );
    }

    if ( get_option( 'whl_redirect_admin' ) !== $redirect_slug ) {
        update_option( 'whl_redirect_admin', $redirect_slug );
    }

    if ( is_multisite() ) {
        if ( get_site_option( 'whl_page' ) !== $login_slug ) {
            update_site_option( 'whl_page', $login_slug );
        }

        if ( get_site_option( 'whl_redirect_admin' ) !== $redirect_slug ) {
            update_site_option( 'whl_redirect_admin', $redirect_slug );
        }
    }
}

function pdl_hide_login_admin_conflict_notice( $message ) {
    add_action(
        'admin_notices',
        function () use ( $message ) {
            if ( current_user_can( 'manage_options' ) ) {
                echo '<div class="notice notice-warning"><p>' . esc_html( $message ) . '</p></div>';
            }
        }
    );
}

function pdl_hide_login_should_skip_module() {
    if ( defined( 'PDL_HIDE_LOGIN_DISABLE' ) && PDL_HIDE_LOGIN_DISABLE ) {
        return true;
    }

    if ( pdl_hide_login_regular_plugin_is_active( 'wps-hide-login/wps-hide-login.php' ) ) {
        pdl_hide_login_sync_wps_hide_login_options();
        $GLOBALS['pdl_hide_login_handled_by'] = 'WPS Hide Login';
        return true;
    }

    $conflicting_plugins = apply_filters(
        'pdl_hide_login_conflicting_regular_plugins',
        array(
            'rename-wp-login/rename-wp-login.php' => 'Rename wp-login.php',
        )
    );

    foreach ( $conflicting_plugins as $plugin_file => $plugin_name ) {
        if ( pdl_hide_login_regular_plugin_is_active( $plugin_file ) ) {
            pdl_hide_login_admin_conflict_notice(
                sprintf(
                    'PDL Hide Login đã tạm nhường vì phát hiện plugin %s đang active. Hãy tắt plugin đó hoặc cấu hình nó dùng /%s/.',
                    $plugin_name,
                    pdl_hide_login_slug()
                )
            );

            $GLOBALS['pdl_hide_login_handled_by'] = $plugin_name;
            return true;
        }
    }

    return false;
}

if ( pdl_hide_login_should_skip_module() ) {
    return;
}

function pdl_hide_login_is_wp_login_request( $request ) {
    $request_uri = rawurldecode( $_SERVER['REQUEST_URI'] ?? '' );
    $path        = isset( $request['path'] ) ? untrailingslashit( $request['path'] ) : '';

    return false !== strpos( $request_uri, 'wp-login.php' )
        || $path === site_url( 'wp-login', 'relative' );
}

function pdl_hide_login_is_wp_register_request( $request ) {
    $request_uri = rawurldecode( $_SERVER['REQUEST_URI'] ?? '' );
    $path        = isset( $request['path'] ) ? untrailingslashit( $request['path'] ) : '';

    return false !== strpos( $request_uri, 'wp-register.php' )
        || $path === site_url( 'wp-register', 'relative' );
}

function pdl_hide_login_is_new_login_request( $request ) {
    $path = isset( $request['path'] ) ? untrailingslashit( $request['path'] ) : '';

    return $path === home_url( pdl_hide_login_slug(), 'relative' )
        || (
            ! get_option( 'permalink_structure' )
            && isset( $_GET[ pdl_hide_login_slug() ] )
            && empty( $_GET[ pdl_hide_login_slug() ] )
        );
}

function pdl_hide_login_template_loader_404() {
    global $pagenow;

    $pagenow = 'index.php';

    if ( ! defined( 'WP_USE_THEMES' ) ) {
        define( 'WP_USE_THEMES', true );
    }

    wp();
    status_header( 404 );
    nocache_headers();

    require_once ABSPATH . WPINC . '/template-loader.php';
    exit;
}

function pdl_hide_login_plugins_loaded() {
    global $pagenow;

    $request = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ?? '' ) );

    if ( pdl_hide_login_is_wp_login_request( $request ) && ! is_admin() ) {
        $GLOBALS['pdl_hide_login_wp_login_php'] = true;
        $_SERVER['REQUEST_URI']                 = pdl_hide_login_user_trailingslashit( '/' . str_repeat( '-/', 10 ) );
        $pagenow                                = 'index.php';
    } elseif ( pdl_hide_login_is_new_login_request( $request ) ) {
        $_SERVER['SCRIPT_NAME'] = pdl_hide_login_slug();
        $pagenow                = 'wp-login.php';
    } elseif ( pdl_hide_login_is_wp_register_request( $request ) && ! is_admin() ) {
        $GLOBALS['pdl_hide_login_wp_login_php'] = true;
        $_SERVER['REQUEST_URI']                 = pdl_hide_login_user_trailingslashit( '/' . str_repeat( '-/', 10 ) );
        $pagenow                                = 'index.php';
    }
}
add_action( 'plugins_loaded', 'pdl_hide_login_plugins_loaded', 9999 );

function pdl_hide_login_wp_loaded() {
    global $pagenow;

    $request      = parse_url( rawurldecode( $_SERVER['REQUEST_URI'] ?? '' ) );
    $request_path = $request['path'] ?? '';

    do_action( 'pdl_hide_login_before_redirect', $request );

    if ( isset( $_GET['action'], $_POST['post_password'] ) && 'postpass' === $_GET['action'] ) {
        return;
    }

    if (
        is_admin()
        && ! is_user_logged_in()
        && ! defined( 'WP_CLI' )
        && ! defined( 'DOING_AJAX' )
        && ! defined( 'DOING_CRON' )
        && 'admin-post.php' !== $pagenow
        && '/wp-admin/options.php' !== $request_path
    ) {
        wp_safe_redirect( pdl_hide_login_redirect_url() );
        exit;
    }

    if ( ! is_user_logged_in() && isset( $_GET['wc-ajax'] ) && 'profile.php' === $pagenow ) {
        wp_safe_redirect( pdl_hide_login_redirect_url() );
        exit;
    }

    if ( ! is_user_logged_in() && '/wp-admin/options.php' === $request_path ) {
        header( 'Location: ' . pdl_hide_login_redirect_url() );
        exit;
    }

    if ( 'wp-login.php' === $pagenow && $request_path && $request_path !== pdl_hide_login_user_trailingslashit( $request_path ) && get_option( 'permalink_structure' ) ) {
        wp_safe_redirect(
            pdl_hide_login_user_trailingslashit( pdl_hide_login_url() )
            . ( ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . $_SERVER['QUERY_STRING'] : '' )
        );
        exit;
    }

    if ( ! empty( $GLOBALS['pdl_hide_login_wp_login_php'] ) ) {
        pdl_hide_login_template_loader_404();
    }

    if ( 'wp-login.php' === $pagenow ) {
        $redirect_to           = admin_url();
        $requested_redirect_to = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : '';

        if ( is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) {
            $user = wp_get_current_user();
            wp_safe_redirect( apply_filters( 'pdl_hide_login_logged_in_redirect', $redirect_to, $requested_redirect_to, $user ) );
            exit;
        }

        require_once ABSPATH . 'wp-login.php';
        exit;
    }
}
add_action( 'wp_loaded', 'pdl_hide_login_wp_loaded' );

function pdl_hide_login_filter_wp_login_php( $url, $scheme = null ) {
    global $pagenow;

    $origin_url = $url;

    if ( false !== strpos( $url, 'wp-login.php?action=postpass' ) ) {
        return $url;
    }

    if ( is_multisite() && 'install.php' === $pagenow ) {
        return $url;
    }

    if ( false !== strpos( $url, 'wp-login.php' ) && false === strpos( (string) wp_get_referer(), 'wp-login.php' ) ) {
        if ( is_ssl() ) {
            $scheme = 'https';
        }

        $parts = explode( '?', $url, 2 );

        if ( isset( $parts[1] ) ) {
            parse_str( $parts[1], $args );

            if ( isset( $args['login'] ) ) {
                $args['login'] = rawurlencode( $args['login'] );
            }

            $url = add_query_arg( $args, pdl_hide_login_url( $scheme ) );
        } else {
            $url = pdl_hide_login_url( $scheme );
        }
    }

    if ( isset( $_POST['post_password'] ) ) {
        global $current_user;

        if (
            ! is_user_logged_in()
            && isset( $current_user->user_login )
            && is_wp_error( wp_authenticate_username_password( null, $current_user->user_login, wp_unslash( $_POST['post_password'] ) ) )
        ) {
            return $origin_url;
        }
    }

    if ( ! is_user_logged_in() && file_exists( WP_CONTENT_DIR . '/plugins/gravityforms/gravityforms.php' ) && isset( $_GET['gf_page'] ) ) {
        return $origin_url;
    }

    return $url;
}

function pdl_hide_login_site_url( $url, $path, $scheme, $blog_id ) {
    return pdl_hide_login_filter_wp_login_php( $url, $scheme );
}
add_filter( 'site_url', 'pdl_hide_login_site_url', 10, 4 );

function pdl_hide_login_network_site_url( $url, $path, $scheme ) {
    return pdl_hide_login_filter_wp_login_php( $url, $scheme );
}
add_filter( 'network_site_url', 'pdl_hide_login_network_site_url', 10, 3 );

function pdl_hide_login_wp_redirect( $location, $status ) {
    if ( false !== strpos( $location, 'https://wordpress.com/wp-login.php' ) ) {
        return $location;
    }

    return pdl_hide_login_filter_wp_login_php( $location );
}
add_filter( 'wp_redirect', 'pdl_hide_login_wp_redirect', 10, 2 );

function pdl_hide_login_login_url( $login_url, $redirect, $force_reauth ) {
    if ( is_404() || false === $force_reauth || empty( $redirect ) ) {
        return is_404() ? '#' : $login_url;
    }

    $redirect_parts = explode( '?', $redirect, 2 );

    if ( admin_url( 'options.php' ) === $redirect_parts[0] ) {
        return admin_url();
    }

    return $login_url;
}
add_filter( 'login_url', 'pdl_hide_login_login_url', 10, 3 );

function pdl_hide_login_user_request_action_email_content( $email_text, $email_data ) {
    if ( isset( $email_data['confirm_url'] ) ) {
        $email_text = str_replace(
            '###CONFIRM_URL###',
            esc_url_raw( str_replace( pdl_hide_login_slug() . '/', 'wp-login.php', $email_data['confirm_url'] ) ),
            $email_text
        );
    }

    return $email_text;
}
add_filter( 'user_request_action_email_content', 'pdl_hide_login_user_request_action_email_content', 999, 2 );

function pdl_hide_login_welcome_email( $value ) {
    return str_replace( 'wp-login.php', trailingslashit( pdl_hide_login_slug() ), $value );
}
add_filter( 'site_option_welcome_email', 'pdl_hide_login_welcome_email' );

function pdl_hide_login_redirect_export_data() {
    if ( empty( $_GET['action'] ) || 'confirmaction' !== $_GET['action'] || empty( $_GET['request_id'] ) || empty( $_GET['confirm_key'] ) ) {
        return;
    }

    $request_id = (int) $_GET['request_id'];
    $key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );
    $result     = wp_validate_user_request_key( $request_id, $key );

    if ( ! is_wp_error( $result ) ) {
        wp_redirect(
            add_query_arg(
                array(
                    'action'      => 'confirmaction',
                    'request_id'  => $request_id,
                    'confirm_key' => $key,
                ),
                pdl_hide_login_url()
            )
        );
        exit;
    }
}
add_action( 'template_redirect', 'pdl_hide_login_redirect_export_data' );

function pdl_hide_login_setup_theme() {
    global $pagenow;

    if ( ! is_user_logged_in() && 'customize.php' === $pagenow ) {
        wp_die( 'This has been disabled', '', array( 'response' => 403 ) );
    }
}
add_action( 'setup_theme', 'pdl_hide_login_setup_theme', 1 );

function pdl_hide_login_block_signup_access() {
    $request_uri = rawurldecode( $_SERVER['REQUEST_URI'] ?? '' );

    if (
        ! is_multisite()
        && ( false !== strpos( $request_uri, 'wp-signup' ) || false !== strpos( $request_uri, 'wp-activate' ) )
        && false === apply_filters( 'pdl_hide_login_signup_enable', false )
    ) {
        wp_die( 'This feature is not enabled.' );
    }
}
add_action( 'init', 'pdl_hide_login_block_signup_access' );

remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
