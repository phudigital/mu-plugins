<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function pdl_login_logo_url() {
    $logo_id = (int) get_theme_mod( 'custom_logo' );

    if ( ! $logo_id ) {
        $logo_id = (int) get_option( 'site_logo' );
    }

    if ( $logo_id ) {
        $logo = wp_get_attachment_image_src( $logo_id, 'full' );

        if ( ! empty( $logo[0] ) ) {
            return $logo[0];
        }
    }

    $site_icon = get_site_icon_url( 192 );

    if ( $site_icon ) {
        return $site_icon;
    }

    return '';
}

function pdl_login_styles() {
    $logo_url  = pdl_login_logo_url();
    $site_name = get_bloginfo( 'name' );
    ?>
    <style>
        :root {
            --pdl-login-ink: #273253;
            --pdl-login-muted: #6f778f;
            --pdl-login-line: #dce1ec;
            --pdl-login-panel: rgba(255, 255, 255, .94);
            --pdl-login-accent: #2f6fb3;
        }

        body.login {
            min-height: 100vh;
            background:
                radial-gradient(circle at 12% 18%, rgba(47, 111, 179, .16), transparent 28%),
                radial-gradient(circle at 82% 14%, rgba(39, 50, 83, .12), transparent 26%),
                linear-gradient(145deg, #f4f7fb 0%, #e8edf5 48%, #f8fafc 100%);
            color: var(--pdl-login-ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        body.login #login {
            width: min(390px, calc(100vw - 32px));
            padding: clamp(42px, 8vh, 72px) 0 32px;
        }

        body.login h1 {
            margin-bottom: 22px;
        }

        body.login h1 a {
            display: flex !important;
            align-items: center;
            justify-content: center;
            width: 220px;
            height: 104px;
            margin: 0 auto;
            background-image: <?php echo $logo_url ? 'url("' . esc_url( $logo_url ) . '")' : 'none'; ?>;
            background-position: center;
            background-repeat: no-repeat;
            background-size: contain;
            color: var(--pdl-login-ink);
            font-size: 0;
            font-weight: 800;
            line-height: 1.2;
            overflow: visible;
            text-indent: 0;
            text-decoration: none;
            pointer-events: auto;
        }

        body.login h1 a::before {
            content: "<?php echo esc_js( $site_name ); ?>";
            display: <?php echo $logo_url ? 'none' : 'block'; ?>;
            text-indent: 0;
            font-size: 22px;
        }

        body.login h1 a .pdl-login-logo-img {
            display: block;
            width: auto;
            max-width: 220px;
            height: auto;
            max-height: 104px;
            object-fit: contain;
        }

        body.login form {
            margin-top: 0;
            padding: 28px;
            border: 1px solid rgba(220, 225, 236, .95);
            border-radius: 16px;
            background: var(--pdl-login-panel);
            box-shadow: 0 22px 55px rgba(39, 50, 83, .14);
            backdrop-filter: blur(10px);
        }

        body.login form .input,
        body.login input[type="text"],
        body.login input[type="password"] {
            min-height: 44px;
            border: 1px solid #c8cfdd;
            border-radius: 10px;
            background: #fff;
            color: var(--pdl-login-ink);
            font-size: 16px;
            box-shadow: none;
        }

        body.login form .input:focus,
        body.login input[type="text"]:focus,
        body.login input[type="password"]:focus {
            border-color: var(--pdl-login-accent);
            box-shadow: 0 0 0 3px rgba(47, 111, 179, .16);
            outline: none;
        }

        body.login label {
            color: var(--pdl-login-ink);
            font-size: 13px;
            font-weight: 650;
        }

        body.login .button.wp-hide-pw {
            color: var(--pdl-login-accent);
        }

        body.login .button-primary {
            min-height: 42px;
            padding: 0 18px;
            border: 0;
            border-radius: 10px;
            background: var(--pdl-login-ink);
            font-weight: 700;
            box-shadow: 0 12px 24px rgba(39, 50, 83, .22);
        }

        body.login .button-primary:hover,
        body.login .button-primary:focus {
            background: #1f2946;
            box-shadow: 0 14px 28px rgba(39, 50, 83, .28);
        }

        body.login .forgetmenot {
            display: flex;
            align-items: center;
            min-height: 42px;
        }

        body.login input[type="checkbox"] {
            width: 18px;
            height: 18px;
            border-color: #b8c0d1;
            border-radius: 5px;
            box-shadow: none;
        }

        body.login .message,
        body.login .notice,
        body.login .success {
            border-left-color: var(--pdl-login-accent);
            border-radius: 10px;
            background: rgba(255, 255, 255, .92);
            box-shadow: 0 10px 30px rgba(39, 50, 83, .08);
        }

        body.login #nav,
        body.login #backtoblog,
        body.login .privacy-policy-page-link,
        body.login .language-switcher {
            text-align: center;
        }

        body.login .language-switcher {
            display: flex;
            justify-content: center;
            width: min(390px, calc(100vw - 32px));
            margin-right: auto;
            margin-left: auto;
        }

        body.login #nav a,
        body.login #backtoblog a,
        body.login .privacy-policy-page-link a {
            color: var(--pdl-login-muted);
            font-weight: 600;
            text-decoration: none;
        }

        body.login #nav a:hover,
        body.login #backtoblog a:hover,
        body.login .privacy-policy-page-link a:hover {
            color: var(--pdl-login-ink);
        }

        body.login .language-switcher select {
            min-height: 38px;
            border-color: #c8cfdd;
            border-radius: 10px;
        }

        .pdl-login-branding {
            display: block;
            width: 100%;
            margin: 14px auto 0;
            padding: 0 16px;
            box-sizing: border-box;
            color: var(--pdl-login-muted);
            font-size: 12px;
            font-weight: 600;
            letter-spacing: .01em;
            text-align: center;
        }

        .pdl-login-branding a {
            color: var(--pdl-login-ink);
            font-weight: 800;
            text-decoration: none;
        }

        .pdl-login-branding a:hover {
            color: var(--pdl-login-accent);
        }

        @media (max-width: 480px) {
            body.login #login {
                padding-top: 32px;
            }

            body.login form {
                padding: 22px;
                border-radius: 14px;
            }

            body.login h1 a {
                width: 190px;
                height: 88px;
            }
        }
    </style>
    <?php
}
add_action( 'login_enqueue_scripts', 'pdl_login_styles', 20 );

function pdl_login_header_url() {
    return home_url( '/' );
}
add_filter( 'login_headerurl', 'pdl_login_header_url' );

function pdl_login_header_text() {
    return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'pdl_login_header_text' );

function pdl_login_branding() {
    ?>
    <div class="pdl-login-branding">
        Phát triển bởi <a href="https://pdl.vn" target="_blank" rel="noopener noreferrer">PDL Solutions</a> (Phú Digital)
    </div>
    <?php
}
add_action( 'login_footer', 'pdl_login_branding', 20 );

function pdl_login_logo_image_fallback() {
    $logo_url  = pdl_login_logo_url();
    $site_name = get_bloginfo( 'name' );

    if ( ! $logo_url ) {
        return;
    }
    ?>
    <script>
    (function(){
        var link = document.querySelector('.login h1.wp-login-logo a, .login h1 a');

        if(!link || link.querySelector('.pdl-login-logo-img')){
            return;
        }

        link.style.backgroundImage = 'none';
        link.textContent = '';

        var image = document.createElement('img');
        image.className = 'pdl-login-logo-img';
        image.src = <?php echo wp_json_encode( esc_url( $logo_url ) ); ?>;
        image.alt = <?php echo wp_json_encode( $site_name ); ?>;
        image.loading = 'eager';
        image.decoding = 'async';

        link.appendChild(image);
    })();
    </script>
    <?php
}
add_action( 'login_footer', 'pdl_login_logo_image_fallback', 5 );
